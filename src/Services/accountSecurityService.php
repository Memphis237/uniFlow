<?php

declare(strict_types=1);

require_once __DIR__ . '/authService.php';

function account_security_start_email_change(
    PDO $database,
    array $user,
    string $newEmail
): string {
    $newEmail = strtolower(trim($newEmail));
    if (
        !filter_var($newEmail, FILTER_VALIDATE_EMAIL)
        || strlen($newEmail) > 100
    ) {
        throw new RuntimeException('Saisis une adresse e-mail valide.');
    }
    if ($newEmail === strtolower((string) $user['email'])) {
        throw new RuntimeException('Cette adresse est déjà associée à ton compte.');
    }
    if (auth_model_email_exists($database, $newEmail)) {
        throw new RuntimeException('Cette adresse e-mail est déjà utilisée.');
    }

    email_verification_issue(
        $database,
        'email_change',
        (int) $user['Id_users'],
        (string) $user['email'],
        ['email' => $newEmail]
    );

    return $newEmail;
}

function account_security_verify_current_email(
    PDO $database,
    array $user,
    string $code
): string {
    $challenge = email_verification_consume(
        $database,
        'email_change',
        (int) $user['Id_users'],
        (string) $user['email'],
        $code
    );
    $newEmail = strtolower((string) ($challenge['pending_email'] ?? ''));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('L’adresse e-mail demandée est invalide.');
    }
    if (auth_model_email_exists($database, $newEmail)) {
        throw new RuntimeException('Cette adresse e-mail est déjà utilisée.');
    }

    email_verification_issue(
        $database,
        'email_change_confirm',
        (int) $user['Id_users'],
        $newEmail,
        ['email' => $newEmail]
    );

    return $newEmail;
}

function account_security_verify_new_email(
    PDO $database,
    array $user,
    string $newEmail,
    string $code
): void {
    $challenge = email_verification_consume(
        $database,
        'email_change_confirm',
        (int) $user['Id_users'],
        $newEmail,
        $code
    );
    $verifiedEmail = strtolower((string) ($challenge['pending_email'] ?? ''));
    if ($verifiedEmail !== strtolower($newEmail)) {
        throw new RuntimeException('La vérification de cette adresse a échoué.');
    }
    if (auth_model_email_exists($database, $verifiedEmail)) {
        throw new RuntimeException('Cette adresse e-mail est déjà utilisée.');
    }

    auth_model_update_email($database, (int) $user['Id_users'], $verifiedEmail);
}

function account_security_start_password_change(
    PDO $database,
    array $user,
    string $password,
    string $confirmation
): void {
    if ($password !== $confirmation) {
        throw new RuntimeException('Les deux mots de passe ne correspondent pas.');
    }
    if (strlen($password) < 8 || strlen($password) > 4096) {
        throw new RuntimeException(
            'Le mot de passe doit contenir au moins 8 caractères.'
        );
    }

    email_verification_issue(
        $database,
        'password_change',
        (int) $user['Id_users'],
        (string) $user['email'],
        ['password' => password_hash($password, PASSWORD_DEFAULT)]
    );
}

function account_security_finish_password_change(
    PDO $database,
    array $user,
    string $code
): void {
    $challenge = email_verification_consume(
        $database,
        'password_change',
        (int) $user['Id_users'],
        (string) $user['email'],
        $code
    );
    $passwordHash = (string) ($challenge['pending_password'] ?? '');
    if ($passwordHash === '') {
        throw new RuntimeException('La demande de changement est invalide.');
    }

    auth_model_update_password(
        $database,
        (int) $user['Id_users'],
        $passwordHash
    );
}

function account_security_resend(
    PDO $database,
    array $user,
    string $kind,
    array $state
): void {
    $userId = (int) $user['Id_users'];
    if ($kind === 'email' && ($state['stage'] ?? '') === 'current') {
        $purpose = 'email_change';
        $address = (string) $user['email'];
        $pendingData = ['email' => (string) ($state['email'] ?? '')];
    } elseif ($kind === 'email' && ($state['stage'] ?? '') === 'confirm') {
        $purpose = 'email_change_confirm';
        $address = (string) ($state['email'] ?? '');
        $pendingData = ['email' => $address];
    } elseif ($kind === 'password' && ($state['stage'] ?? '') === 'verify') {
        $purpose = 'password_change';
        $address = (string) $user['email'];
        $pendingData = [];
    } else {
        throw new RuntimeException('Aucune vérification à renouveler.');
    }

    $challenge = email_verification_model_find(
        $database,
        $purpose,
        $userId,
        $address
    );
    if (!$challenge) {
        throw new RuntimeException('Aucun code actif. Relance la demande.');
    }
    if ($kind === 'password') {
        $pendingData['password'] = $challenge['pending_password'];
    }

    email_verification_issue(
        $database,
        $purpose,
        $userId,
        $address,
        $pendingData
    );
}

function account_security_cancel(
    PDO $database,
    array $user,
    string $kind,
    array $state
): void {
    $userId = (int) $user['Id_users'];
    if ($kind === 'email' && ($state['stage'] ?? '') === 'current') {
        email_verification_model_delete(
            $database,
            'email_change',
            $userId,
            (string) $user['email']
        );
    } elseif ($kind === 'email' && ($state['stage'] ?? '') === 'confirm') {
        email_verification_model_delete(
            $database,
            'email_change_confirm',
            $userId,
            (string) ($state['email'] ?? '')
        );
    } elseif ($kind === 'password' && ($state['stage'] ?? '') === 'verify') {
        email_verification_model_delete(
            $database,
            'password_change',
            $userId,
            (string) $user['email']
        );
    } else {
        throw new RuntimeException('Aucune vérification à annuler.');
    }
}
