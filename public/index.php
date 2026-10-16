<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database/db.php';
require_once __DIR__ . '/../src/Services/authService.php';

if (($_GET['api'] ?? '') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['api'] === 'auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $user = auth_handle((string) ($input['action'] ?? 'login'), (string) ($input['name'] ?? ''), (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));
            echo json_encode(['token' => auth_create_token($user)], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($_GET['api'] === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $user = auth_user_from_authorization();
            if ($user === null) http_response_code(401);
            echo json_encode(['user' => $user], JSON_THROW_ON_ERROR);
            exit;
        }
        http_response_code(404);
        echo json_encode(['error' => 'Requête inconnue.']);
    } catch (RuntimeException $exception) {
        http_response_code(400);
        echo json_encode(['error' => $exception->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        http_response_code(500);
        echo json_encode(['error' => 'Une erreur est survenue. Réessayez.']);
    }
    exit;
}

if (($_GET['page'] ?? '') === 'home') {
    require __DIR__ . '/../src/Views/pages/home.php';
    exit;
}

$mode = (($_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
$error = '';
$oldName = '';
$oldEmail = '';
$csrf = auth_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (($_POST['action'] ?? '') === 'register') ? 'register' : 'login';
    $oldName = trim((string) ($_POST['name'] ?? ''));
    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Votre session a expiré. Rechargez la page et réessayez.');
        }
        $user = auth_handle($mode, $oldName, $oldEmail, (string) ($_POST['password'] ?? ''));
        $loginToken = auth_create_token($user);
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        $error = 'Une erreur est survenue. Vérifiez la configuration de la base de données puis réessayez.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087e8b">
    <title><?= $mode === 'register' ? 'Créer un compte' : 'Connexion' ?> — UniFlow</title>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="welcome-panel" aria-label="Présentation de UniFlow">
            <a class="brand" href="index.php" aria-label="UniFlow, accueil"><span class="brand-mark"><img src="./assets/images/UniFlow_logo.png" alt="Logo UniFlow"></span><span>Uni<span>Flow</span></span></a>
            <div class="welcome-copy">
                <span class="eyebrow">LE CAMPUS, EN MOUVEMENT</span>
                <h1>Faites circuler<br>les bonnes idées.</h1>
                <p>Les talents de votre campus se rencontrent ici. Partagez, apprenez et avancez ensemble.</p>
            </div>
            <div class="campus-note"><span class="status-dot"></span> Votre communauté commence ici</div>
            <span class="decor decor-one"></span><span class="decor decor-two"></span>
        </section>

        <section class="form-panel">
            <div class="mobile-brand"><span class="brand-mark">u</span><span>uni<span>flow</span></span></div>
            <div class="form-content">
                <div class="form-heading">
                    <span class="eyebrow teal"><?= $mode === 'register' ? 'BIENVENUE DANS LA COMMUNAUTÉ' : 'HEUREUX DE VOUS REVOIR' ?></span>
                    <h2><?= $mode === 'register' ? 'Créer votre compte' : 'Connectez-vous' ?></h2>
                    <p><?= $mode === 'register' ? 'Quelques informations et vous êtes prêt à démarrer.' : 'Retrouvez votre espace UniFlow en quelques secondes.' ?></p>
                </div>

                <div class="tabs" role="tablist" aria-label="Choisir une action">
                    <a role="tab" aria-selected="<?= $mode === 'login' ? 'true' : 'false' ?>" class="tab <?= $mode === 'login' ? 'active' : '' ?>" href="index.php?mode=login">Connexion</a>
                    <a role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>" class="tab <?= $mode === 'register' ? 'active' : '' ?>" href="index.php?mode=register">Inscription</a>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="index.php?mode=<?= $mode ?>" class="auth-form">
                    <input type="hidden" name="action" value="<?= $mode ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($mode === 'register'): ?>
                    <label for="name">Nom complet</label>
                    <div class="input-wrap"><span class="input-icon" aria-hidden="true">✳</span><input id="name" name="name" type="text" autocomplete="name" placeholder="Ex. Camille Martin" value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <?php endif; ?>
                    <label for="email">Adresse e-mail</label>
                    <div class="input-wrap"><span class="input-icon" aria-hidden="true">＠</span><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <div class="password-label"><label for="password">Mot de passe</label><?php if ($mode === 'login'): ?><span class="secure-note">Protégé et confidentiel</span><?php endif; ?></div>
                    <div class="input-wrap"><span class="input-icon lock" aria-hidden="true">▣</span><input id="password" name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" placeholder="<?= $mode === 'register' ? '8 caractères minimum' : 'Votre mot de passe' ?>" required minlength="<?= $mode === 'register' ? '8' : '1' ?>"><button class="reveal" type="button" aria-label="Afficher le mot de passe">Afficher</button></div>
                    <?php if ($mode === 'register'): ?><p class="hint">Utilisez au moins 8 caractères.</p><?php endif; ?>
                    <button class="submit-button" type="submit"><?= $mode === 'register' ? 'Créer mon compte' : 'Se connecter' ?><span aria-hidden="true">→</span></button>
                </form>
                <p class="switch-copy"><?= $mode === 'register' ? 'Vous avez déjà un compte ?' : 'Vous découvrez UniFlow ?' ?> <a href="index.php?mode=<?= $mode === 'register' ? 'login' : 'register' ?>"><?= $mode === 'register' ? 'Se connecter' : 'Créer un compte' ?></a></p>
                <div class="form-footer"><span>UNIFLOW</span><span>Un campus. Mille possibilités.</span></div>
            </div>
        </section>
    </main>
    <script>
    const loginToken = <?= json_encode($loginToken ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (loginToken) {
        localStorage.setItem('uniflow_token', loginToken);
        window.location.replace('index.php?page=home');
    }
    document.querySelector('.reveal')?.addEventListener('click', function () {
        const field = document.querySelector('#password');
        const shown = field.type === 'password';
        field.type = shown ? 'text' : 'password';
        this.textContent = shown ? 'Masquer' : 'Afficher';
        this.setAttribute('aria-label', shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
    </script>
</body>
</html>
