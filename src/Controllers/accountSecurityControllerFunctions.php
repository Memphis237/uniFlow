<?php

declare(strict_types=1);

require_once __DIR__ . '/../Services/accountSecurityService.php';

function account_security_controller_dispatch(PDO $database, array $user): never
{
    auth_require_csrf_token();
    $userId = (int) $user['Id_users'];
    $action = (string) ($_POST['action'] ?? '');
    if (!isset($_SESSION['account_security'][$userId])) {
        $_SESSION['account_security'][$userId] = [];
    }
    $security = &$_SESSION['account_security'][$userId];

    try {
        if ($action === 'security-email-start') {
            $newEmail = account_security_start_email_change(
                $database,
                $user,
                (string) ($_POST['new_email'] ?? '')
            );
            $security['email'] = [
                'stage' => 'current',
                'email' => $newEmail,
                'resend_at' => time() + 60,
            ];
            $notice = 'Un code a été envoyé à ton adresse actuelle.';
        } elseif ($action === 'security-email-verify') {
            $emailState = $security['email'] ?? [];
            if (($emailState['stage'] ?? '') === 'current') {
                $newEmail = account_security_verify_current_email(
                    $database,
                    $user,
                    (string) ($_POST['code'] ?? '')
                );
                $security['email'] = [
                    'stage' => 'confirm',
                    'email' => $newEmail,
                    'resend_at' => time() + 60,
                ];
                $notice = 'Un second code a été envoyé à la nouvelle adresse.';
            } elseif (($emailState['stage'] ?? '') === 'confirm') {
                account_security_verify_new_email(
                    $database,
                    $user,
                    (string) ($emailState['email'] ?? ''),
                    (string) ($_POST['code'] ?? '')
                );
                unset($security['email']);
                $notice = 'Ton adresse e-mail a été modifiée et vérifiée.';
            } else {
                throw new RuntimeException('Aucun changement d’adresse en attente.');
            }
        } elseif ($action === 'security-password-start') {
            account_security_start_password_change(
                $database,
                $user,
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );
            $security['password'] = [
                'stage' => 'verify',
                'resend_at' => time() + 60,
            ];
            $notice = 'Un code a été envoyé à ton adresse e-mail.';
        } elseif ($action === 'security-password-verify') {
            account_security_finish_password_change(
                $database,
                $user,
                (string) ($_POST['code'] ?? '')
            );
            unset($security['password']);
            $notice = 'Ton mot de passe a été modifié.';
        } elseif ($action === 'security-resend') {
            $kind = (string) ($_POST['kind'] ?? '');
            $state = $security[$kind] ?? [];
            account_security_resend($database, $user, $kind, $state);
            $security[$kind]['resend_at'] = time() + 60;
            $notice = 'Un nouveau code vient de t’être envoyé.';
        } elseif ($action === 'security-cancel') {
            $kind = (string) ($_POST['kind'] ?? '');
            $state = $security[$kind] ?? [];
            account_security_cancel($database, $user, $kind, $state);
            unset($security[$kind]);
            $notice = 'La vérification a été annulée.';
        } else {
            throw new RuntimeException('Action de sécurité inconnue.');
        }

        $_SESSION['account_security_flash'] = [
            'notice' => $notice,
            'error' => '',
        ];
    } catch (RuntimeException $exception) {
        $_SESSION['account_security_flash'] = [
            'notice' => '',
            'error' => $exception->getMessage(),
        ];
        $message = $exception->getMessage();
        if (str_contains($message, 'Aucun code actif')) {
            if ($action === 'security-email-verify') {
                unset($security['email']);
            } elseif ($action === 'security-password-verify') {
                unset($security['password']);
            }
        } elseif (
            $action === 'security-email-verify'
            && isset($security['email'])
            && !str_starts_with($message, 'Code incorrect')
            && !str_starts_with($message, 'Ce code a expiré')
            && !str_starts_with($message, 'Saisis le code')
        ) {
            unset($security['email']);
        }
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        if ($action === 'security-email-verify') {
            unset($security['email']);
        } elseif ($action === 'security-password-verify') {
            unset($security['password']);
        }
        $_SESSION['account_security_flash'] = [
            'notice' => '',
            'error' => 'Une erreur est survenue. Vérifie la configuration SMTP puis réessaie.',
        ];
    }

    header('Location: index.php?page=profile#account-security');
    exit;
}
