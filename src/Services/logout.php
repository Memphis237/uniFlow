<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/logoutModel.php';
require_once __DIR__ . '/authService.php';
require_once __DIR__ . '/chatService.php';

function logout_service_complete(PDO $database): void
{
    try {
        $user = auth_user_from_cookie();
        if ($user !== null) {
            $conversations = logout_model_mark_offline(
                $database,
                (int) $user['Id_users']
            );

            if ($conversations !== []) {
                chat_ws_publish([
                    'type' => 'presence',
                    'conversations' => $conversations,
                    'userId' => (int) $user['Id_users'],
                    'online' => false,
                ]);
            }
        }
    } catch (Throwable $exception) {
        error_log('Presence logout update failed: ' . $exception->getMessage());
    }

    auth_clear_cookie();
}
