<?php
declare(strict_types=1);

function chat_model_is_member(PDO $database, int $userId, int $conversationId): bool
{
    $query = $database->prepare(
        'SELECT 1 FROM lier '
        . 'WHERE Id_users = :user AND Id_conversations = :conversation'
    );
    $query->execute([
        'user' => $userId,
        'conversation' => $conversationId,
    ]);

    return (bool) $query->fetchColumn();
}

function chat_model_set_presence(PDO $database, int $userId, bool $online): array
{
    $query = $database->prepare(
        'UPDATE Users SET is_online = :online, last_seen = NOW() '
        . 'WHERE Id_users = :id'
    );
    $query->execute(['online' => $online ? 1 : 0, 'id' => $userId]);

    if ($online) {
        $database->prepare(
            'UPDATE Messages m '
            . 'JOIN lier l ON l.Id_conversations = m.Id_conversations '
            . 'SET m.delivered_at = NOW() '
            . 'WHERE l.Id_users = :recipient AND m.Id_users <> :sender '
            . 'AND m.delivered_at IS NULL'
        )->execute(['recipient' => $userId, 'sender' => $userId]);
    }

    $query = $database->prepare(
        'SELECT Id_conversations FROM lier WHERE Id_users = :id'
    );
    $query->execute(['id' => $userId]);

    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
}

function chat_model_unread_counts(PDO $database, int $userId): array
{
    $query = $database->prepare(
        'SELECT m.Id_conversations, COUNT(*) AS unread '
        . 'FROM Messages m '
        . 'JOIN lier l ON l.Id_conversations = m.Id_conversations '
        . 'WHERE l.Id_users = :user AND m.Id_users <> :sender '
        . 'AND m.read_at IS NULL GROUP BY m.Id_conversations'
    );
    $query->execute(['user' => $userId, 'sender' => $userId]);

    $counts = [];
    foreach ($query->fetchAll() as $row) {
        $counts[(string) $row['Id_conversations']] = (int) $row['unread'];
    }

    return $counts;
}

function chat_model_mark_read(
    PDO $database,
    int $conversationId,
    int $userId
): int {
    $query = $database->prepare(
        'UPDATE Messages SET read_at = NOW() '
        . 'WHERE Id_conversations = :conversation '
        . 'AND Id_users <> :user AND read_at IS NULL'
    );
    $query->execute(['conversation' => $conversationId, 'user' => $userId]);

    return $query->rowCount();
}

function chat_model_messages(PDO $database, int $conversationId): array
{
    $query = $database->prepare(
        'SELECT m.Id_messages, m.content, m.created_at, m.delivered_at, '
        . 'm.read_at, m.Id_users, u.first_name, u.name '
        . 'FROM Messages m JOIN Users u ON u.Id_users = m.Id_users '
        . 'WHERE m.Id_conversations = :conversation ORDER BY m.Id_messages'
    );
    $query->execute(['conversation' => $conversationId]);
    $messages = $query->fetchAll();
    $attachments = $database->prepare(
        'SELECT Id_messages, name_original, file_path, mime_type, file_size '
        . 'FROM Message_attachments WHERE Id_messages = :message'
    );

    foreach ($messages as &$message) {
        $attachments->execute(['message' => $message['Id_messages']]);
        $message['attachments'] = $attachments->fetchAll();
    }
    unset($message);

    return $messages;
}

function chat_model_peer_presence(
    PDO $database,
    int $conversationId,
    int $userId
): array {
    $query = $database->prepare(
        'SELECT MAX(u.is_online = 1 '
        . 'AND u.last_seen >= NOW() - INTERVAL 45 SECOND) AS is_online, '
        . 'MAX(u.last_seen) AS last_seen '
        . 'FROM lier l JOIN Users u ON u.Id_users = l.Id_users '
        . 'WHERE l.Id_conversations = :conversation AND l.Id_users <> :user'
    );
    $query->execute(['conversation' => $conversationId, 'user' => $userId]);

    return $query->fetch() ?: [];
}

function chat_model_save_message(
    PDO $database,
    int $userId,
    int $conversationId,
    string $content,
    array $attachments
): int {
    $database->beginTransaction();
    $movedFiles = [];

    try {
        $query = $database->prepare(
            'INSERT INTO Messages(content, Id_users, Id_conversations) '
            . 'VALUES (:content, :user, :conversation)'
        );
        $query->execute([
            'content' => $content,
            'user' => $userId,
            'conversation' => $conversationId,
        ]);
        $messageId = (int) $database->lastInsertId();

        if ($attachments !== []) {
            $directory = dirname(__DIR__, 2) . '/public/uploads/messages';
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Stockage des pièces jointes indisponible.');
            }

            $insert = $database->prepare(
                'INSERT INTO Message_attachments '
                . '(Id_messages, name_original, file_path, mime_type, file_size) '
                . 'VALUES (:message, :name, :path, :mime, :size)'
            );
            foreach ($attachments as $attachment) {
                $filename = bin2hex(random_bytes(20)) . '.' . $attachment['ext'];
                $path = $directory . '/' . $filename;
                if (!move_uploaded_file($attachment['tmp'], $path)) {
                    throw new RuntimeException('Échec de sauvegarde d’une pièce jointe.');
                }
                $movedFiles[] = $path;
                $insert->execute([
                    'message' => $messageId,
                    'name' => $attachment['name'],
                    'path' => 'uploads/messages/' . $filename,
                    'mime' => $attachment['mime'],
                    'size' => $attachment['size'],
                ]);
            }
        }

        $database->commit();

        return $messageId;
    } catch (Throwable $exception) {
        $database->rollBack();
        foreach ($movedFiles as $path) {
            @unlink($path);
        }

        throw $exception;
    }
}

function chat_model_recipients(
    PDO $database,
    int $conversationId,
    int $userId
): array {
    $query = $database->prepare(
        'SELECT Id_users FROM lier '
        . 'WHERE Id_conversations = :conversation AND Id_users <> :user'
    );
    $query->execute(['conversation' => $conversationId, 'user' => $userId]);

    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
}

function chat_model_update_delivery(PDO $database, int $messageId, bool $recipientOnline): void
{
    if ($recipientOnline) {
        $database->prepare(
            'UPDATE Messages SET delivered_at = NOW() WHERE Id_messages = :id'
        )->execute(['id' => $messageId]);
        return;
    }

    $query = $database->prepare(
        'UPDATE Messages m '
        . 'JOIN lier l ON l.Id_conversations = m.Id_conversations '
        . 'JOIN Users recipient ON recipient.Id_users = l.Id_users '
        . 'SET m.delivered_at = NOW() '
        . 'WHERE m.Id_messages = :message AND l.Id_users <> m.Id_users '
        . 'AND recipient.is_online = 1'
    );
    $query->execute(['message' => $messageId]);
}

function chat_model_message(PDO $database, int $messageId): ?array
{
    $query = $database->prepare(
        'SELECT m.Id_messages, m.content, m.created_at, m.delivered_at, '
        . 'm.read_at, m.Id_users, u.first_name, u.name '
        . 'FROM Messages m JOIN Users u ON u.Id_users = m.Id_users '
        . 'WHERE m.Id_messages = :id'
    );
    $query->execute(['id' => $messageId]);
    $message = $query->fetch();
    if (!$message) {
        return null;
    }

    $attachments = $database->prepare(
        'SELECT name_original, file_path, mime_type, file_size '
        . 'FROM Message_attachments WHERE Id_messages = :id'
    );
    $attachments->execute(['id' => $messageId]);
    $message['attachments'] = $attachments->fetchAll();

    return $message;
}
