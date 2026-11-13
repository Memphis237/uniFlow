<?php

declare(strict_types=1);

function auth_controller_dispatch(): void
{
    require_once dirname(__DIR__) . '/database/db.php';
    require_once dirname(__DIR__) . '/Services/authService.php';
    $mode = in_array(
        (string) ($_GET['mode'] ?? 'login'),
        ['login', 'register', 'forgot'],
        true
    ) ? (string) ($_GET['mode'] ?? 'login') : 'login';
    $error = '';
    $successMessage = '';
    $oldName = '';
    $oldFirstName = '';
    $oldEmail = '';
    $csrf = auth_csrf_token();
    $loginLockedUntil = 0;
    $loginWarning = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? 'login');
        $mode = in_array(
            $action,
            ['register', 'verify-signup', 'resend-signup'],
            true
        ) ? 'register' : (in_array(
            $action,
            ['forgot-password', 'resend-password-reset', 'verify-password-reset'],
            true
        ) ? 'forgot' : 'login');
        $oldName = trim((string) ($_POST['name'] ?? ''));
        $oldFirstName = trim((string) ($_POST['first_name'] ?? ''));
        $oldEmail = trim((string) ($_POST['email'] ?? ''));
        try {
            if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Votre session a expiré. Rechargez la page et réessayez.');
            }
            if ($action === 'register') {
                auth_registration_start(
                    $oldName,
                    $oldFirstName,
                    $oldEmail,
                    (string) ($_POST['password'] ?? '')
                );
                $_SESSION['pending_signup_email'] = strtolower($oldEmail);
                $_SESSION['pending_signup_resend_at'] = time() + 60;
                $successMessage = 'Un code de vérification vient d’être envoyé à cette adresse.';
            } elseif ($action === 'verify-signup') {
                $pendingEmail = (string) ($_SESSION['pending_signup_email'] ?? '');
                if ($pendingEmail === '') {
                    throw new RuntimeException('Aucune inscription en attente. Recommence le formulaire.');
                }
                $user = auth_registration_verify(
                    $pendingEmail,
                    (string) ($_POST['code'] ?? '')
                );
                unset(
                    $_SESSION['pending_signup_email'],
                    $_SESSION['pending_signup_resend_at']
                );
                auth_set_cookie($user);
                $redirectPage = 'home';
            } elseif ($action === 'resend-signup') {
                $pendingEmail = (string) ($_SESSION['pending_signup_email'] ?? '');
                if ($pendingEmail === '') {
                    throw new RuntimeException('Aucune inscription en attente. Recommence le formulaire.');
                }
                auth_registration_resend($pendingEmail);
                $_SESSION['pending_signup_resend_at'] = time() + 60;
                $successMessage = 'Un nouveau code vient d’être envoyé.';
            } elseif ($action === 'forgot-password') {
                auth_password_reset_start($oldEmail);
                $_SESSION['pending_password_reset_email'] = strtolower($oldEmail);
                $_SESSION['password_reset_resend_at'] = time() + 60;
                $successMessage = 'Si cette adresse correspond à un compte actif, un code vient d’être envoyé.';
            } elseif ($action === 'resend-password-reset') {
                $pendingEmail = (string) ($_SESSION['pending_password_reset_email'] ?? '');
                if ($pendingEmail === '') {
                    throw new RuntimeException('Recommence la demande de réinitialisation.');
                }
                auth_password_reset_resend($pendingEmail);
                $_SESSION['password_reset_resend_at'] = time() + 60;
                $successMessage = 'Si cette adresse correspond à un compte actif, un nouveau code a été envoyé.';
            } elseif ($action === 'verify-password-reset') {
                $pendingEmail = (string) ($_SESSION['pending_password_reset_email'] ?? '');
                if ($pendingEmail === '') {
                    throw new RuntimeException('Recommence la demande de réinitialisation.');
                }
                auth_password_reset_complete(
                    $pendingEmail,
                    (string) ($_POST['code'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirmation'] ?? '')
                );
                unset(
                    $_SESSION['pending_password_reset_email'],
                    $_SESSION['password_reset_resend_at']
                );
                auth_clear_cookie();
                $mode = 'login';
                $successMessage = 'Votre mot de passe a été modifié. Vous pouvez vous connecter.';
            } else {
                $user = auth_handle(
                    'login',
                    $oldName,
                    $oldEmail,
                    (string) ($_POST['password'] ?? ''),
                    $oldFirstName
                );
                auth_set_cookie($user);
                $redirectPage = ($user['role'] ?? '') === 'admin' ? 'admin' : 'home';
            }
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            if ($message === 'locked:900') {
                $loginLockedUntil = auth_login_attempt_state()['locked_until'];
                $error = '';
            } elseif (str_starts_with($message, 'warning:')) {
                $loginWarning = true;
                $error = substr($message, 8);
            } else {
                $error = $message;
            }
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            $error = 'Une erreur est survenue. Vérifiez la configuration de la base de données puis réessayez.';
        }
    }
    if (isset($redirectPage)) {
        header('Location: index.php?page=' . rawurlencode($redirectPage));
        exit;
    }
    if ($mode === 'login' && $loginLockedUntil === 0) {
        $attemptState = auth_login_attempt_state();
        $loginLockedUntil = $attemptState['locked_until'];
    }
    render_view('pages/auth', [
        'mode' => $mode,
        'error' => $error,
        'successMessage' => $successMessage,
        'oldName' => $oldName,
        'oldFirstName' => $oldFirstName,
        'oldEmail' => $oldEmail,
        'csrf' => $csrf,
        'loginLockedUntil' => $loginLockedUntil,
        'loginWarning' => $loginWarning,
        'signupPending' => $mode === 'register'
            && !empty($_SESSION['pending_signup_email']),
        'verificationEmail' => (string) ($_SESSION['pending_signup_email'] ?? ''),
        'signupResendAt' => (int) ($_SESSION['pending_signup_resend_at'] ?? 0),
        'forgotPending' => !empty($_SESSION['pending_password_reset_email']),
        'forgotEmail' => (string) ($_SESSION['pending_password_reset_email'] ?? ''),
        'forgotResendAt' => (int) ($_SESSION['password_reset_resend_at'] ?? 0),
    ]);
}
