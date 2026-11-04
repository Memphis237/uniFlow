<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Services/authService.php';

if (isset($_GET['logout'])) {
    setcookie('uniflow_token', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: index.php');
    exit;
}

try {
    $user = auth_user_from_cookie();
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(503);
    exit('Service temporairement indisponible.');
}

if ($user === null) {
    header('Location: index.php?mode=login');
    exit;
}
if (($user['role'] ?? '') !== 'admin') {
    header('Location: index.php?page=home');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration — UniFlow</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="home-page">
    <header class="home-nav">
        <a class="brand" href="index.php?page=admin"><span class="brand-mark">u</span><span>uni<span>flow</span></span></a>
        <a class="logout" href="index.php?page=admin&amp;logout=1">Se déconnecter</a>
    </header>
    <main class="home-content">
        <span class="eyebrow teal">ESPACE ADMINISTRATION</span>
        <p class="home-kicker">Connecté en tant qu’administrateur</p>
        <h1>Bonjour, <?= htmlspecialchars((string) ($user['first_name'] ?? $user['name']), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="home-description">Votre session administrateur UniFlow est active.</p>
    </main>
</body>
</html>
