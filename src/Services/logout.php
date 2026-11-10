<?php
declare(strict_types=1);

require_once __DIR__ . '/authService.php';
require_once __DIR__ . '/chatService.php';

try {
    $logoutUser = auth_user_from_cookie();

    if ($logoutUser) {
        db()->prepare('UPDATE Users SET is_online=0,last_seen=NOW() WHERE Id_users=:id')
            ->execute(['id' => $logoutUser['Id_users']]);

        $query = db()->prepare('SELECT Id_conversations FROM lier WHERE Id_users=:id');
        $query->execute(['id' => $logoutUser['Id_users']]);
        $conversations = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));

        if ($conversations) {
            chat_ws_publish([
                'type' => 'presence',
                'conversations' => $conversations,
                'userId' => (int) $logoutUser['Id_users'],
                'online' => false,
            ]);
        }
    }
} catch (Throwable $exception) {
    error_log('Presence logout update failed: ' . $exception->getMessage());
}

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('uniflow_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
    <meta charset="utf-8">
    <title>DÃ©connexion</title>
    <body>
        <script>
            localStorage.removeItem('uniflow_token');
            window.location.replace('index.php?mode=login');
        </script>
        <noscript><a href="index.php?mode=login">Continuer vers la connexion</a></noscript>
    </body>
</html>
