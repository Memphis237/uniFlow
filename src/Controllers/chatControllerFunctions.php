<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/chatModel.php';
require_once __DIR__ . '/../Services/chatService.php';

function chat_controller_handle_api(string $api, array $actor, PDO $pdo): void
{
    $userId = (int) $actor['Id_users'];

    if ($api === 'chat-token') {
        $conversationId = (int) ($_GET['conversation'] ?? 0);
        if (
            $conversationId > 0
            && !chat_model_is_member($pdo, $userId, $conversationId)
        ) {
            http_response_code(403);
            echo json_encode(['error' => 'Discussion inaccessible.']);
            exit;
        }

        echo json_encode([
            'ticket' => chat_ticket($userId, $conversationId),
        ]);
        exit;
    }

    if ($api === 'chat-presence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $online = ($_POST['state'] ?? 'online') === 'online';
        $conversations = chat_model_set_presence($pdo, $userId, $online);

        if ($conversations !== []) {
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
        $counts = chat_model_unread_counts($pdo, $userId);
        echo json_encode([
            'counts' => $counts,
            'total' => array_sum($counts),
        ]);
        exit;
    }

    $conversationId = (int) (
        $_GET['conversation'] ?? $_POST['conversation'] ?? 0
    );
    if (!chat_model_is_member($pdo, $userId, $conversationId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Discussion inaccessible.']);
        exit;
    }

    if ($api === 'messages-list') {
        if (chat_model_mark_read($pdo, $conversationId, $userId) > 0) {
            chat_ws_publish([
                'type' => 'read',
                'conversation' => $conversationId,
                'userId' => $userId,
            ]);
        }

        echo json_encode([
            'messages' => chat_model_messages($pdo, $conversationId),
            'presence' => chat_model_peer_presence(
                $pdo,
                $conversationId,
                $userId
            ),
        ]);
        exit;
    }

    if ($api !== 'message-send' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    [$content, $attachments] = chat_controller_validate_message(
        (string) ($_POST['content'] ?? ''),
        $_FILES['attachments'] ?? null
    );
    $messageId = chat_model_save_message(
        $pdo,
        $userId,
        $conversationId,
        $content,
        $attachments
    );
    $recipients = chat_model_recipients($pdo, $conversationId, $userId);
    $onlineUsers = chat_ws_publish([
        'type' => 'message',
        'conversation' => $conversationId,
        'sender' => $userId,
        'recipients' => $recipients,
        'messageId' => $messageId,
    ]);
    $recipientOnline = array_intersect($onlineUsers, $recipients) !== [];
    chat_model_update_delivery($pdo, $messageId, $recipientOnline);

    echo json_encode([
        'ok' => true,
        'message' => chat_model_message($pdo, $messageId),
    ]);
    exit;
}

function chat_controller_validate_message(string $content, ?array $files): array
{
    $content = trim($content);
    $attachments = [];

    if ($files !== null && is_array($files['name'] ?? null)) {
        if (count($files['name']) > 5) {
            throw new RuntimeException('Maximum 5 fichiers par message.');
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $totalSize = 0;

        foreach ($files['name'] as $index => $name) {
            if ((int) $files['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ((int) $files['error'][$index] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Un fichier n’a pas pu être téléversé.');
            }

            $size = (int) $files['size'][$index];
            $totalSize += $size;
            if ($size > 8 * 1024 * 1024 || $totalSize > 20 * 1024 * 1024) {
                throw new RuntimeException(
                    'Limite : 8 Mo par fichier et 20 Mo par message.'
                );
            }

            $temporaryPath = (string) $files['tmp_name'][$index];
            $mimeType = $fileInfo->file($temporaryPath);
            $extension = $allowedTypes[$mimeType] ?? null;
            if ($extension === null) {
                throw new RuntimeException(
                    'Formats autorisés : images, PDF et TXT.'
                );
            }

            $attachments[] = [
                'tmp' => $temporaryPath,
                'name' => basename((string) $name),
                'size' => $size,
                'mime' => $mimeType,
                'ext' => $extension,
            ];
        }
    }

    if ($content === '' && $attachments === []) {
        throw new RuntimeException('Écris un message ou joins un fichier.');
    }
    if (mb_strlen($content) > 2000) {
        throw new RuntimeException('Message trop long.');
    }

    return [$content, $attachments];
}
