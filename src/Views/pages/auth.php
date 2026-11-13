<?php

declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);
$modalOpen = ($mode === 'register' && $signupPending)
    || ($mode === 'forgot' && $forgotPending);
$modalEmail = $mode === 'register' ? $verificationEmail : $forgotEmail;
$resendAt = $mode === 'register' ? $signupResendAt : $forgotResendAt;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087e8b">
    <title><?= $mode === 'register' ? 'Créer un compte' : ($mode === 'forgot' ? 'Mot de passe oublié' : 'Connexion') ?> — UniFlow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/auth.css?v=9">
    <script src="assets/js/clear-legacy-auth.js" defer></script>
</head>
<body class="auth-page" data-login-locked-until="<?= (int) $loginLockedUntil ?>">
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
            <div class="form-content">
                <div class="form-heading">
                    <span class="eyebrow teal"><?= $mode === 'register' ? 'BIENVENUE DANS LA COMMUNAUTÉ' : 'HEUREUX DE VOUS REVOIR' ?></span>
                    <h2><?= $mode === 'register' ? 'Créer votre compte' : ($mode === 'forgot' ? 'Réinitialiser le mot de passe' : 'Connectez-vous') ?></h2>
                    <p><?= $mode === 'register' ? 'Quelques informations et vous êtes prêt à démarrer.' : ($mode === 'forgot' ? 'Recevez un code sécurisé pour choisir un nouveau mot de passe.' : 'Retrouvez votre espace UniFlow en quelques secondes.') ?></p>
                </div>
                <?php if ($mode !== 'forgot'): ?>
                    <div class="tabs" role="tablist" aria-label="Choisir une action">
                        <a role="tab" aria-selected="<?= $mode === 'login' ? 'true' : 'false' ?>" class="tab <?= $mode === 'login' ? 'active' : '' ?>" href="index.php?mode=login">Connexion</a>
                        <a role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>" class="tab <?= $mode === 'register' ? 'active' : '' ?>" href="index.php?mode=register">Inscription</a>
                    </div>
                <?php endif; ?>
                <?php if ($error !== '' && !$modalOpen): ?>
                    <div class="alert<?= $loginWarning ? ' login-warning' : '' ?>" role="alert"><?= $escape($error) ?></div>
                <?php endif; ?>
                <?php if ($successMessage !== '' && !$modalOpen): ?>
                    <div class="alert success-alert" role="status"><?= $escape($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($mode === 'forgot' && !$forgotPending): ?>
                    <form method="post" action="index.php?mode=forgot" class="auth-form">
                        <input type="hidden" name="action" value="forgot-password">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                        <label for="email">Adresse e-mail</label>
                        <div class="input-wrap"><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= $escape($oldEmail) ?>" required maxlength="100"></div>
                        <button class="submit-button" type="submit">Recevoir un code <span aria-hidden="true">→</span></button>
                    </form>
                    <p class="switch-copy"><a href="index.php?mode=login">Retour à la connexion</a></p>
                <?php elseif ($mode === 'register' && !$signupPending): ?>
                    <form method="post" action="index.php?mode=register" class="auth-form">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                        <label for="first_name">Prénom</label>
                        <div class="input-wrap"><input id="first_name" name="first_name" type="text" autocomplete="given-name" placeholder="Ex. Camille" value="<?= $escape($oldFirstName) ?>" required maxlength="255"></div>
                        <label for="name">Nom</label>
                        <div class="input-wrap"><input id="name" name="name" type="text" autocomplete="family-name" placeholder="Ex. Martin" value="<?= $escape($oldName) ?>" required maxlength="255"></div>
                        <label for="email">Adresse e-mail</label>
                        <div class="input-wrap"><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= $escape($oldEmail) ?>" required maxlength="100"></div>
                        <label for="password">Mot de passe</label>
                        <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" placeholder="8 caractères minimum" required minlength="8"><button class="reveal" type="button" aria-label="Afficher le mot de passe">Afficher</button></div>
                        <p class="hint">Utilisez au moins 8 caractères.</p>
                        <button class="submit-button" type="submit">Créer mon compte <span aria-hidden="true">→</span></button>
                    </form>
                    <p class="switch-copy">Vous avez déjà un compte ? <a href="index.php?mode=login">Se connecter</a></p>
                <?php elseif ($mode === 'login'): ?>
                    <button class="google-button" type="button" title="La connexion Google nécessite une configuration OAuth"><span class="google-mark" aria-hidden="true">G</span><span>Continuer avec Google</span></button>
                    <div class="social-divider"><span>ou avec ton adresse e-mail</span></div>
                    <form method="post" action="index.php?mode=login" class="auth-form">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                        <label for="email">Adresse e-mail</label>
                        <div class="input-wrap"><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= $escape($oldEmail) ?>" required maxlength="100"></div>
                        <div class="password-label"><label for="password">Mot de passe</label><span class="secure-note">Protégé et confidentiel</span></div>
                        <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Votre mot de passe" required><button class="reveal" type="button" aria-label="Afficher le mot de passe">Afficher</button></div>
                        <p class="forgot-link"><a href="index.php?mode=forgot">Mot de passe oublié ?</a></p>
                        <button class="submit-button" type="submit">Se connecter <span aria-hidden="true">→</span></button>
                    </form>
                    <p class="switch-copy">Vous découvrez UniFlow ? <a href="index.php?mode=register">Créer un compte</a></p>
                <?php endif; ?>
                <div class="form-footer"><span>UNIFLOW</span><span>Un campus. Mille possibilités.</span></div>
            </div>
        </section>
    </main>

    <?php if ($modalOpen): ?>
        <div class="verification-backdrop" role="presentation">
            <section class="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verificationTitle">
                <a class="modal-close" href="index.php?mode=<?= $mode === 'register' ? 'register' : 'forgot' ?>" aria-label="Fermer">×</a>
                <div class="verification-icon" aria-hidden="true">✉</div>
                <p class="modal-eyebrow">VÉRIFICATION SÉCURISÉE</p>
                <h2 id="verificationTitle"><?= $mode === 'register' ? 'Confirmez votre adresse' : 'Choisissez un nouveau mot de passe' ?></h2>
                <p class="modal-description">Un code à 6 chiffres a été envoyé à <strong><?= $escape($modalEmail) ?></strong>. Il expire après 10 minutes.</p>
                <?php if ($error !== ''): ?><div class="alert" role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <?php if ($successMessage !== ''): ?><div class="alert success-alert" role="status"><?= $escape($successMessage) ?></div><?php endif; ?>
                <form method="post" action="index.php?mode=<?= $mode ?>" class="auth-form modal-form">
                    <input type="hidden" name="action" value="<?= $mode === 'register' ? 'verify-signup' : 'verify-password-reset' ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                    <label for="verification_code">Code de vérification</label>
                    <div class="input-wrap code-input-wrap"><input id="verification_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></div>
                    <?php if ($mode === 'forgot'): ?>
                        <label for="password">Nouveau mot de passe</label>
                        <div class="input-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                        <label for="password_confirmation">Confirmer le mot de passe</label>
                        <div class="input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
                    <?php endif; ?>
                    <button class="submit-button" type="submit"><?= $mode === 'register' ? 'Vérifier mon adresse' : 'Modifier mon mot de passe' ?> <span aria-hidden="true">→</span></button>
                </form>
                <form method="post" action="index.php?mode=<?= $mode ?>" class="resend-form">
                    <input type="hidden" name="action" value="<?= $mode === 'register' ? 'resend-signup' : 'resend-password-reset' ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                    <button type="submit" data-resend-button disabled>Renvoyer le code</button>
                    <span data-resend-countdown="<?= (int) $resendAt ?>" aria-live="polite"></span>
                </form>
                <a class="modal-back-link" href="index.php?mode=login">Retour à la connexion</a>
            </section>
        </div>
    <?php endif; ?>
    <script src="assets/js/auth.js" defer></script>
</body>
</html>
