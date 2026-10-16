<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Services/authService.php';

if (isset($_GET['logout'])) {
    setcookie('uniflow_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: index.php');
    exit;
}

try {
    $user = auth_user_from_cookie();
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(503);
    exit('Service temporairement indisponible. Vérifiez la configuration de l’application.');
}

if ($user === null) {
    header('Location: index.php?mode=login');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accueil — UniFlow</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="home-page">
    <header class="home-nav">
        <a class="brand" href="index.php?page=home"><span class="brand-mark">u</span><span>uni<span>flow</span></span></a>
        <a class="logout" href="index.php?page=home&amp;logout=1">Se déconnecter <span aria-hidden="true">↗</span></a>
    </header>
    <main class="home-content">
        <span class="eyebrow teal">VOTRE ESPACE UNIFLOW</span>
        <p class="home-kicker">Ravi de vous retrouver</p>
        <h1>Bonjour, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> <span>👋</span></h1>
        <p class="home-description">Votre espace est prêt. Les belles collaborations commencent par une rencontre.</p>
        <a href="index.php?page=home" class="submit-button home-button">Découvrir UniFlow <span aria-hidden="true">→</span></a>
        <div class="home-card"><span class="home-card-icon">✦</span><div><strong>Tout commence ici.</strong><p>Connectez-vous à votre communauté et donnez vie à vos projets.</p></div></div>
    </main>
</body>
</html>
