<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/emailVerificationModel.php';

function email_verification_code_hash(string $code): string
{
    return hash_hmac('sha256', $code, auth_jwt_secret());
}

function email_verification_send_email(string $address, string $code): void
{
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException(
            'Les dépendances Composer ne sont pas installées.'
        );
    }
    require_once $autoload;

    $host = app_env('SMTP_HOST', '');
    $username = app_env('SMTP_USERNAME', '');
    $password = app_env('SMTP_PASSWORD', '');
    $from = app_env('SMTP_FROM_EMAIL', '');
    $fromName = app_env('SMTP_FROM_NAME', 'UniFlow');
    $port = (int) app_env('SMTP_PORT', '587');
    $encryption = strtolower((string) app_env('SMTP_ENCRYPTION', 'tls'));

    if (
        $host === ''
        || $from === ''
        || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || !in_array($encryption, ['tls', 'ssl', 'none'], true)
        || ($username !== '' && $encryption === 'none')
        || $port < 1
        || $port > 65535
    ) {
        throw new RuntimeException(
            'La configuration SMTP est incomplète. Vérifie les paramètres SMTP du fichier .env.'
        );
    }

    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->Timeout = 20;
        $mailer->SMTPAuth = $username !== '';
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mailer->SMTPSecure = match ($encryption) {
            'tls' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            'ssl' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };
        $mailer->setFrom($from, $fromName ?: 'UniFlow');
        $mailer->addAddress($address);
        $mailer->isHTML(true);
        $mailer->Subject = 'Votre code de vérification UniFlow';
        $mailer->Body = '<p>Voici votre code de vérification UniFlow :</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:8px">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            . '</p><p>Ce code expire dans 10 minutes. Si vous n’êtes pas à l’origine '
            . 'de cette demande, ignorez ce message.</p>';
        $mailer->AltBody = 'Votre code de vérification UniFlow est ' . $code
            . '. Il expire dans 10 minutes.';
        $mailer->send();
    } catch (Throwable $exception) {
        error_log('Échec de l’envoi du code de vérification : ' . $exception->getMessage());
        throw new RuntimeException(
            'Impossible d’envoyer le code. Vérifie la configuration SMTP puis réessaie.'
        );
    }
}

function email_verification_issue(
    PDO $database,
    string $purpose,
    ?int $userId,
    string $email,
    array $pendingData = []
): int {
    $existing = email_verification_model_find(
        $database,
        $purpose,
        $userId,
        $email
    );
    if ($existing) {
        $lastSentAt = strtotime($existing['last_sent_at'] . ' UTC');
        $remaining = 60 - (time() - $lastSentAt);
        if ($remaining > 0) {
            throw new RuntimeException(
                'Attends ' . $remaining . ' seconde(s) avant de demander un nouveau code.'
            );
        }
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    email_verification_send_email($email, $code);
    email_verification_model_store(
        $database,
        $userId,
        $purpose,
        $email,
        email_verification_code_hash($code),
        $pendingData
    );

    return 60;
}

function email_verification_consume(
    PDO $database,
    string $purpose,
    ?int $userId,
    string $email,
    string $code
): array {
    if (!preg_match('/^\d{6}$/', $code)) {
        throw new RuntimeException('Saisis le code à 6 chiffres reçu par e-mail.');
    }

    $result = email_verification_model_consume(
        $database,
        $purpose,
        $userId,
        $email,
        email_verification_code_hash($code)
    );

    if ($result['status'] === 'verified') {
        return $result['challenge'];
    }
    if ($result['status'] === 'expired') {
        throw new RuntimeException(
            'Ce code a expiré. Demande un nouveau code pour continuer.'
        );
    }
    if ($result['status'] === 'attempts_exhausted') {
        throw new RuntimeException(
            'Les 5 tentatives sont épuisées. Recommence la demande de vérification.'
        );
    }
    if ($result['status'] === 'invalid') {
        throw new RuntimeException(
            'Code incorrect. Il te reste ' . $result['remaining'] . ' tentative(s).'
        );
    }

    throw new RuntimeException(
        'Aucun code actif. Demande un nouveau code pour continuer.'
    );
}
