<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../Models/authModel.php';

function auth_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function auth_require_csrf_token(): void
{
    $expected = auth_csrf_token();
    $provided = (string) (
        $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''
    );

    if ($provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        throw new RuntimeException(
            'Jeton de sécurité invalide. Recharge la page et réessaie.'
        );
    }
}

function auth_jwt_secret(): string
{
    $secret = app_env('JWT_SECRET');
    if (
        $secret === null
        || strlen($secret) < 32
        || str_contains($secret, 'remplacer_par_une_cle_')
    ) {
        throw new RuntimeException(
            'JWT_SECRET doit être défini dans le fichier .env avec au moins 32 caractères.'
        );
    }

    return $secret;
}

function auth_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function auth_create_token(array $user): string
{
    $now = time();
    $header = auth_base64url_encode(json_encode([
        'typ' => 'JWT',
        'alg' => 'HS256',
    ], JSON_THROW_ON_ERROR));
    $payload = auth_base64url_encode(json_encode([
        'sub' => (int) $user['Id_users'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'student',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_THROW_ON_ERROR));
    $signature = auth_base64url_encode(hash_hmac(
        'sha256',
        $header . '.' . $payload,
        auth_jwt_secret(),
        true
    ));

    return $header . '.' . $payload . '.' . $signature;
}

function auth_decode_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;
    try {
        $headerData = json_decode(
            base64_decode(strtr($header, '-_', '+/')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $claims = json_decode(
            base64_decode(strtr($payload, '-_', '+/')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $expected = auth_base64url_encode(hash_hmac(
            'sha256',
            $header . '.' . $payload,
            auth_jwt_secret(),
            true
        ));

        if (
            ($headerData['alg'] ?? '') !== 'HS256'
            || !hash_equals($expected, $signature)
            || !is_array($claims)
            || (int) ($claims['exp'] ?? 0) <= time()
            || empty($claims['sub'])
        ) {
            return null;
        }

        return $claims;
    } catch (Throwable $exception) {
        return null;
    }
}

function auth_user_from_cookie(): ?array
{
    $token = $_COOKIE['uniflow_token'] ?? '';
    if (!is_string($token) || $token === '') {
        return null;
    }

    $claims = auth_decode_token($token);
    if ($claims === null) {
        return null;
    }

    return auth_model_find_active_by_id(db(), (int) $claims['sub']);
}

function auth_set_cookie(array $user): void
{
    setcookie('uniflow_token', auth_create_token($user), [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_clear_cookie(): void
{
    setcookie('uniflow_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_login_attempt_state(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $state = $_SESSION['auth_login_attempts'] ?? [
        'count' => 0,
        'locked_until' => 0,
    ];
    if (!is_array($state)) {
        $state = ['count' => 0, 'locked_until' => 0];
    }

    $state['count'] = max(0, (int) ($state['count'] ?? 0));
    $state['locked_until'] = max(0, (int) ($state['locked_until'] ?? 0));
    if ($state['locked_until'] > 0 && $state['locked_until'] <= time()) {
        $state = ['count' => 0, 'locked_until' => 0];
    }

    $_SESSION['auth_login_attempts'] = $state;

    return $state;
}

function auth_handle(
    string $action,
    string $name,
    string $email,
    string $password,
    string $firstName = ''
): array {
    if (!in_array($action, ['login', 'register'], true)) {
        throw new RuntimeException('Action d’authentification inconnue.');
    }

    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        throw new RuntimeException('Veuillez saisir une adresse e-mail valide.');
    }
    if ($password === '' || strlen($password) > 4096) {
        throw new RuntimeException('Veuillez saisir un mot de passe valide.');
    }

    $database = db();
    if ($action === 'register') {
        return auth_register($database, $name, $firstName, $email, $password);
    }

    return auth_login($database, $email, $password);
}

function auth_register(
    PDO $database,
    string $name,
    string $firstName,
    string $email,
    string $password
): array {
    $name = trim($name);
    $firstName = trim($firstName);
    if ($name === '' || strlen($name) > 255) {
        throw new RuntimeException(
            'Veuillez saisir votre nom complet (255 caractères maximum).'
        );
    }
    if (strlen($password) < 8) {
        throw new RuntimeException(
            'Votre mot de passe doit contenir au moins 8 caractères.'
        );
    }
    if ($firstName === '' || strlen($firstName) > 255) {
        throw new RuntimeException('Veuillez saisir votre prénom.');
    }
    if (auth_model_email_exists($database, $email)) {
        throw new RuntimeException(
            'Cette adresse e-mail possède déjà un compte.'
        );
    }

    $userId = auth_model_create_student(
        $database,
        $name,
        $firstName,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    );

    return [
        'Id_users' => $userId,
        'name' => $name,
        'first_name' => $firstName,
        'email' => $email,
        'role' => 'student',
    ];
}

function auth_login(PDO $database, string $email, string $password): array
{
    $attemptState = auth_login_attempt_state();
    if ($attemptState['locked_until'] > time()) {
        $remaining = $attemptState['locked_until'] - time();
        throw new RuntimeException(
            'Connexion temporairement bloquée. Réessayez dans '
            . ceil($remaining / 60) . ' minute(s).'
        );
    }

    $user = auth_model_find_active_by_email($database, $email);
    if (!$user || !password_verify($password, $user['password'])) {
        $attemptState['count']++;
        if ($attemptState['count'] >= 5) {
            $attemptState['locked_until'] = time() + 900;
            $_SESSION['auth_login_attempts'] = $attemptState;
            throw new RuntimeException('locked:900');
        }

        $_SESSION['auth_login_attempts'] = $attemptState;
        if ($attemptState['count'] === 3) {
            throw new RuntimeException(
                'warning:Adresse e-mail ou mot de passe incorrect. '
                . 'Attention, il vous reste 2 essais avant le blocage temporaire '
                . 'de 15 minutes.'
            );
        }
        throw new RuntimeException('Adresse e-mail ou mot de passe incorrect.');
    }

    $_SESSION['auth_login_attempts'] = ['count' => 0, 'locked_until' => 0];
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        auth_model_update_password(
            $database,
            (int) $user['Id_users'],
            password_hash($password, PASSWORD_DEFAULT)
        );
    }

    unset($user['password']);

    return $user;
}
