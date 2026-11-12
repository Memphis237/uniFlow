<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087e8b">
    <title><?= $mode === 'register' ? 'Créer un compte' : 'Connexion' ?> — UniFlow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/auth.css?v=7">
    <script src="assets/js/clear-legacy-auth.js" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="welcome-panel" aria-label="Présentation de UniFlow">
            <a class="brand" href="index.php" aria-label="UniFlow, accueil"><img class="auth-logo" width="68" height="68" src="assets/images/UniFlow_logo1_clean.png" alt="UniFlow"></a>
            <div class="welcome-copy">
                <span class="eyebrow">LE CAMPUS, EN MOUVEMENT</span>
                <h1>Faites circuler<br>les bonnes idées.</h1>
                <p>Les talents de votre campus se rencontrent ici. Partagez, apprenez et avancez ensemble.</p>
            </div>
            <div class="campus-note"><span class="status-dot"></span> Votre communauté commence ici</div>
            <span class="decor decor-one"></span><span class="decor decor-two"></span>
        </section>

        <section class="form-panel">
            <!-- <div class="mobile-brand"><img class="auth-logo" width="68" height="68" src="assets/images/UniFlow_logo1_clean.png" alt="UniFlow"></div> -->
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

                <button class="google-button" type="button" title="La connexion Google nécessite une configuration OAuth">
                    <span class="google-mark" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </button>
                <div class="social-divider"><span>ou avec ton adresse e-mail</span></div>

                <?php if ($error !== ''): ?>
                    <div class="alert<?= $loginWarning ? ' login-warning' : '' ?>" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="index.php?mode=<?= $mode ?>" class="auth-form">
                    <input type="hidden" name="action" value="<?= $mode ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($mode === 'register'): ?>
                    <label for="first_name">Prénom</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.8-3.3 3.2-5 7-5s6.2 1.7 7 5"></path></svg><input id="first_name" name="first_name" type="text" autocomplete="given-name" placeholder="Ex. Camille" value="<?= htmlspecialchars($oldFirstName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <label for="name">Nom</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.8-3.3 3.2-5 7-5s6.2 1.7 7 5"></path></svg><input id="name" name="name" type="text" autocomplete="family-name" placeholder="Ex. Martin" value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <?php endif; ?>
                    <label for="email">Adresse e-mail</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="3"></rect><path d="m5 7 7 5 7-5"></path></svg><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <div class="password-label"><label for="password">Mot de passe</label><?php if ($mode === 'login'): ?><span class="secure-note">Protégé et confidentiel</span><?php endif; ?></div>
                    <div class="input-wrap"><svg class="input-icon lock" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10" width="15" height="11" rx="2.5"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v3"></path></svg><input id="password" name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" placeholder="<?= $mode === 'register' ? '8 caractères minimum' : 'Votre mot de passe' ?>" required minlength="<?= $mode === 'register' ? '8' : '1' ?>"><button class="reveal" type="button" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><svg class="eye-open" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="eye-closed" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10 10 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"></path></svg></button></div>
                    <?php if ($mode === 'register'): ?><p class="hint">Utilisez au moins 8 caractères.</p><?php endif; ?>
                    <button class="submit-button" type="submit"><?= $mode === 'register' ? 'Créer mon compte' : 'Se connecter' ?><span aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></button>
                </form>
                <p class="switch-copy"><?= $mode === 'register' ? 'Vous avez déjà un compte ?' : 'Vous découvrez UniFlow ?' ?> <a href="index.php?mode=<?= $mode === 'register' ? 'login' : 'register' ?>"><?= $mode === 'register' ? 'Se connecter' : 'Créer un compte' ?></a></p>
                <div class="form-footer"><span>UNIFLOW</span><span>Un campus. Mille possibilités.</span></div>
            </div>
        </section>
    </main>
    <script>
    const loginLockedUntil = <?= (int) $loginLockedUntil ?>;
    if (loginLockedUntil > Math.floor(Date.now() / 1000)) {
        const overlay = document.createElement('div');
        overlay.className = 'lock-modal-backdrop';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'lockModalTitle');
        overlay.innerHTML = '<section class="lock-modal"><div class="lock-icon"><i class="fa-solid fa-shield-halved"></i></div><p class="lock-eyebrow">SÉCURITÉ UNIFLOW</p><h2 id="lockModalTitle">Connexion temporairement bloquée</h2><p>Trop de tentatives incorrectes. Vous pourrez réessayer dans :</p><div class="lock-countdown" aria-live="polite"><span id="lockTimer">15:00</span></div><small>Cette mesure protège votre compte.</small></section>';
        document.body.append(overlay);
        document.querySelectorAll('.auth-form input, .auth-form button[type="submit"]').forEach(element => element.disabled = true);
        const timer = document.getElementById('lockTimer');
        const updateLockTimer = () => {
            const remaining = Math.max(0, loginLockedUntil - Math.floor(Date.now() / 1000));
            timer.textContent = String(Math.floor(remaining / 60)).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0');
            if (remaining === 0) window.location.reload();
        };
        updateLockTimer();
        window.setInterval(updateLockTimer, 1000);
    }
    document.querySelector('.reveal')?.addEventListener('click', function () {
        const field = document.querySelector('#password');
        const shown = field.type === 'password';
        field.type = shown ? 'text' : 'password';
        this.setAttribute('aria-label', shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        this.setAttribute('title', shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        this.classList.toggle('is-visible', shown);
    });
    </script>
</body>
</html>
