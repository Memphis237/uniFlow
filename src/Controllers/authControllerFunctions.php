<?php
declare(strict_types=1);

function auth_controller_dispatch(): void
{
    require_once dirname(__DIR__) . '/database/db.php';
    require_once dirname(__DIR__) . '/Services/authService.php';
    $mode = (($_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
    $error = '';
    $oldName = '';
    $oldFirstName = '';
    $oldEmail = '';
    $csrf = auth_csrf_token();
    $loginLockedUntil = 0;
    $loginWarning = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = (($_POST['action'] ?? '') === 'register') ? 'register' : 'login';
        $oldName = trim((string) ($_POST['name'] ?? ''));
        $oldFirstName = trim((string) ($_POST['first_name'] ?? ''));
        $oldEmail = trim((string) ($_POST['email'] ?? ''));
        try {
            if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
                throw new RuntimeException('Votre session a expiré. Rechargez la page et réessayez.');
            }
            $user = auth_handle($mode, $oldName, $oldEmail, (string) ($_POST['password'] ?? ''), $oldFirstName);
            auth_set_cookie($user);
            $redirectPage = ($user['role'] ?? '') === 'admin' ? 'admin' : 'home';
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
        'oldName' => $oldName,
        'oldFirstName' => $oldFirstName,
        'oldEmail' => $oldEmail,
        'csrf' => $csrf,
        'loginLockedUntil' => $loginLockedUntil,
        'loginWarning' => $loginWarning,
    ]);
}
