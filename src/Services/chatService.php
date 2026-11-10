<?php
declare(strict_types=1);

function chat_ws_publish(array $event): array
{
    $secret = app_env('JWT_SECRET', '');
    if (strlen((string) $secret) < 32) {
        return [];
    }
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $key = hash_hmac('sha256', 'uniflow-ws-internal', (string) $secret);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 0.35,
            'header' => "Content-Type: application/json\r\nX-UniFlow-Key: {$key}\r\n",
            'content' => $body,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents('http://127.0.0.1:8081/publish', false, $context);
    $data = is_string($response) ? json_decode($response, true) : null;
    return is_array($data) && isset($data['onlineUsers']) ? array_map('intval', $data['onlineUsers']) : [];
}

function chat_ticket(int $userId, int $conversationId): string
{
    $secret = (string) app_env('JWT_SECRET', '');
    $claims = [
        'user' => $userId,
        'conversation' => $conversationId,
        'exp' => time() + 600,
    ];
    $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    return $payload . '.' . hash_hmac(
        'sha256',
        $payload,
        hash_hmac('sha256', 'uniflow-ws-ticket', $secret)
    );
}

function chat_handle_api(string $api, array $actor, PDO $pdo): void
{
    $userId = (int) $actor['Id_users'];

    if ($api === 'chat-token') {
        $conversation = (int) ($_GET['conversation'] ?? 0);
        if ($conversation > 0) {
            $query = $pdo->prepare(
                'SELECT 1 FROM lier WHERE Id_users=:user AND Id_conversations=:conversation'
            );
            $query->execute(['user' => $userId, 'conversation' => $conversation]);
            if (!$query->fetchColumn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Discussion inaccessible.']);
                exit;
            }
        }

        echo json_encode(['ticket' => chat_ticket($userId, $conversation)]);
        exit;
    }

    if ($api === 'chat-presence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $online = ($_POST['state'] ?? 'online') === 'online';
        $query = $pdo->prepare('UPDATE Users SET is_online=:online,last_seen=NOW() WHERE Id_users=:id');
        $query->execute(['online' => $online ? 1 : 0, 'id' => $userId]);

        if ($online) {
            $pdo->prepare(
                'UPDATE Messages m
                 JOIN lier l ON l.Id_conversations=m.Id_conversations
                 SET m.delivered_at=NOW()
                 WHERE l.Id_users=:recipient AND m.Id_users<>:sender AND m.delivered_at IS NULL'
            )->execute(['recipient' => $userId, 'sender' => $userId]);
        }

        $query = $pdo->prepare('SELECT Id_conversations FROM lier WHERE Id_users=:id');
        $query->execute(['id' => $userId]);
        $conversations = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        if ($conversations) {
            chat_ws_publish([
                'type' => $online ? 'delivery' : 'presence',
                'conversations' => $conversations,
                'userId' => $userId,
                'online' => $online,
            ]);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($api === 'messages-unread') {
        $query = $pdo->prepare(
            'SELECT m.Id_conversations,COUNT(*) unread
             FROM Messages m JOIN lier l ON l.Id_conversations=m.Id_conversations
             WHERE l.Id_users=:user AND m.Id_users<>:sender AND m.read_at IS NULL
             GROUP BY m.Id_conversations'
        );
        $query->execute(['user' => $userId, 'sender' => $userId]);
        $counts = [];
        foreach ($query->fetchAll() as $row) {
            $counts[(string) $row['Id_conversations']] = (int) $row['unread'];
        }

        echo json_encode(['counts' => $counts, 'total' => array_sum($counts)]);
        exit;
    }

    $conversation = (int) ($_GET['conversation'] ?? $_POST['conversation'] ?? 0);
    $member = $pdo->prepare('SELECT 1 FROM lier WHERE Id_users=:user AND Id_conversations=:conversation');
    $member->execute(['user' => $userId, 'conversation' => $conversation]);
    if (!$member->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['error' => 'Discussion inaccessible.']);
        exit;
    }

    if ($api === 'messages-list') {
        $read = $pdo->prepare(
            'UPDATE Messages SET read_at=NOW()
             WHERE Id_conversations=:conversation AND Id_users<>:user AND read_at IS NULL'
        );
        $read->execute(['conversation' => $conversation, 'user' => $userId]);
        if ($read->rowCount()) {
            chat_ws_publish([
                'type' => 'read',
                'conversation' => $conversation,
                'userId' => $userId,
            ]);
        }

        $query = $pdo->prepare(
            'SELECT m.Id_messages,m.content,m.created_at,m.delivered_at,m.read_at,m.Id_users,
                    u.first_name,u.name
             FROM Messages m JOIN Users u ON u.Id_users=m.Id_users
             WHERE m.Id_conversations=:conversation ORDER BY m.Id_messages'
        );
        $query->execute(['conversation' => $conversation]);
        $messages = $query->fetchAll();
        $attachments = $pdo->prepare(
            'SELECT Id_messages,name_original,file_path,mime_type,file_size
             FROM Message_attachments WHERE Id_messages=:message'
        );
        foreach ($messages as &$message) {
            $attachments->execute(['message' => $message['Id_messages']]);
            $message['attachments'] = $attachments->fetchAll();
        }
        unset($message);

        $peer = $pdo->prepare(
            'SELECT MAX(u.is_online=1 AND u.last_seen>=NOW()-INTERVAL 45 SECOND) is_online,
                    MAX(u.last_seen) last_seen
             FROM lier l JOIN Users u ON u.Id_users=l.Id_users
             WHERE l.Id_conversations=:conversation AND l.Id_users<>:user'
        );
        $peer->execute(['conversation' => $conversation, 'user' => $userId]);
        echo json_encode(['messages' => $messages, 'presence' => $peer->fetch()]);
        exit;
    }

    if ($api === 'message-send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = trim((string) ($_POST['content'] ?? ''));
        $files = $_FILES['attachments'] ?? null;
        $items = [];

        if ($files && is_array($files['name'] ?? null)) {
            if (count($files['name']) > 5) {
                throw new RuntimeException('Maximum 5 fichiers par message.');
            }
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'text/plain' => 'txt',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $total = 0;

            foreach ($files['name'] as $i => $name) {
                if ((int) $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Un fichier n a pas pu etre televerse.');
                }

                $size = (int) $files['size'][$i];
                $total += $size;
                if ($size > 8 * 1024 * 1024 || $total > 20 * 1024 * 1024) {
                    throw new RuntimeException('Limite: 8 Mo par fichier et 20 Mo par message.');
                }

                $mime = $finfo->file((string) $files['tmp_name'][$i]);
                $extension = $allowed[$mime] ?? null;
                if (!$extension) {
                    throw new RuntimeException('Formats autorises: images, PDF et TXT.');
                }
                $items[] = [
                    'tmp' => $files['tmp_name'][$i],
                    'name' => basename((string) $name),
                    'size' => $size,
                    'mime' => $mime,
                    'ext' => $extension,
                ];
            }
        }

        if ($content === '' && !$items) {
            throw new RuntimeException('Ecris un message ou joins un fichier.');
        }
        if (mb_strlen($content) > 2000) {
            throw new RuntimeException('Message trop long.');
        }

        $pdo->beginTransaction();
        $moved = [];
        try {
            $insert = $pdo->prepare(
                'INSERT INTO Messages(content,Id_users,Id_conversations)
                 VALUES(:content,:user,:conversation)'
            );
            $insert->execute([
                'content' => $content,
                'user' => $userId,
                'conversation' => $conversation,
            ]);
            $messageId = (int) $pdo->lastInsertId();

            if ($items) {
                $dir = dirname(__DIR__, 2) . '/public/uploads/messages';
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    throw new RuntimeException('Stockage des pieces jointes indisponible.');
                }
                $attachment = $pdo->prepare(
                    'INSERT INTO Message_attachments(Id_messages,name_original,file_path,mime_type,file_size)
                     VALUES(:message,:name,:path,:mime,:size)'
                );
                foreach ($items as $item) {
                    $filename = bin2hex(random_bytes(20)) . '.' . $item['ext'];
                    $path = $dir . '/' . $filename;
                    if (!move_uploaded_file($item['tmp'], $path)) {
                        throw new RuntimeException('Echec de sauvegarde d une piece jointe.');
                    }
                    $moved[] = $path;
                    $attachment->execute([
                        'message' => $messageId,
                        'name' => $item['name'],
                        'path' => 'uploads/messages/' . $filename,
                        'mime' => $item['mime'],
                        'size' => $item['size'],
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            foreach ($moved as $path) {
                @unlink($path);
            }
            throw $exception;
        }

        $query = $pdo->prepare(
            'SELECT Id_users FROM lier WHERE Id_conversations=:conversation AND Id_users<>:user'
        );
        $query->execute(['conversation' => $conversation, 'user' => $userId]);
        $recipients = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        $online = chat_ws_publish([
            'type' => 'message',
            'conversation' => $conversation,
            'sender' => $userId,
            'recipients' => $recipients,
            'messageId' => $messageId,
        ]);

        if (array_intersect($online, $recipients)) {
            $pdo->prepare('UPDATE Messages SET delivered_at=NOW() WHERE Id_messages=:id')
                ->execute(['id' => $messageId]);
        } else {
            $delivered = $pdo->prepare(
                'UPDATE Messages m
                 JOIN lier l ON l.Id_conversations=m.Id_conversations
                 JOIN Users recipient ON recipient.Id_users=l.Id_users
                 SET m.delivered_at=NOW()
                 WHERE m.Id_messages=:message AND l.Id_users<>m.Id_users AND recipient.is_online=1'
            );
            $delivered->execute(['message' => $messageId]);
        }

        $query = $pdo->prepare(
            'SELECT m.Id_messages,m.content,m.created_at,m.delivered_at,m.read_at,m.Id_users,
                    u.first_name,u.name
             FROM Messages m JOIN Users u ON u.Id_users=m.Id_users
             WHERE m.Id_messages=:id'
        );
        $query->execute(['id' => $messageId]);
        $message = $query->fetch();
        $query = $pdo->prepare(
            'SELECT name_original,file_path,mime_type,file_size
             FROM Message_attachments WHERE Id_messages=:id'
        );
        $query->execute(['id' => $messageId]);
        $message['attachments'] = $query->fetchAll();

        echo json_encode(['ok' => true, 'message' => $message]);
        exit;
    }
}
