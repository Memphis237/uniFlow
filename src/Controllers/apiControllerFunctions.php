<?php
declare(strict_types=1);

function api_controller_dispatch(): void
{
    header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/database/db.php';
    require_once dirname(__DIR__) . '/Services/authService.php';
    require_once dirname(__DIR__) . '/Services/chatService.php';
    require_once dirname(__DIR__) . '/Models/serviceModel.php';
    require_once dirname(__DIR__) . '/Models/chatModel.php';
    require_once __DIR__ . '/chatControllerFunctions.php';
    require_once __DIR__ . '/serviceControllerFunctions.php';
    require_once __DIR__ . '/serviceControllerFunctions.php';

    try {
        $api = (string) ($_GET['api'] ?? '');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            auth_require_csrf_token();
        }

        $allowedApis = [
            'service-create', 'service-like', 'service-detail',
            'booking-create', 'review-create', 'contact',
            'messages-list', 'message-send', 'messages-unread',
            'chat-token', 'chat-presence',
        ];
        if (!in_array($api, $allowedApis, true)) {
            api_controller_respond(['error' => 'Requête inconnue.'], 404);
        }

        $actor = auth_user_from_cookie();
        if ($actor === null) {
            api_controller_respond(
                ['error' => 'Connecte-toi pour continuer.'],
                401
            );
        }

        $database = db();
        $chatApis = [
            'messages-list', 'message-send', 'messages-unread',
            'chat-token', 'chat-presence',
        ];
        if (in_array($api, $chatApis, true)) {
            chat_controller_handle_api($api, $actor, $database);
        } elseif (service_controller_handle_api($api, $actor, $database)) {
            return;
        }

        api_controller_respond(['error' => 'Requête inconnue.'], 404);
    } catch (RuntimeException $exception) {
        $statusCode = http_response_code() === 403 ? 403 : 400;
        api_controller_respond(['error' => $exception->getMessage()], $statusCode);
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        $message = ($_GET['api'] ?? '') === 'service-create'
            ? 'Le service n’a pas pu être enregistré. Vérifie les tables Services, Categories et Media dans schema.sql, puis consulte le journal PHP pour le détail.'
            : 'Une erreur est survenue. Réessayez.';
        api_controller_respond(['error' => $message], 500);
    }
}

function api_controller_respond(array $payload, int $statusCode): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
