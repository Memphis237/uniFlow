<?php
declare(strict_types=1);

function logout_controller_dispatch(): void
{
    require_once dirname(__DIR__) . '/database/db.php';
    require_once dirname(__DIR__) . '/Services/authService.php';
    require_once dirname(__DIR__) . '/Services/chatService.php';
    require_once dirname(__DIR__) . '/Services/logout.php';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Méthode non autorisée.');
    }

    try {
        auth_require_csrf_token();
    } catch (RuntimeException $exception) {
        http_response_code(403);
        exit($exception->getMessage());
    }

    logout_service_complete(db());
    header('Location: index.php?mode=login');
    exit;
}
