<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

function auth_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function auth_jwt_secret(): string
{
    $secret = app_env('JWT_SECRET');
    if ($secret === null || strlen($secret) < 32 || str_contains($secret, 'remplacer_par_une_cle_')) {
        throw new RuntimeException('JWT_SECRET doit être défini dans le fichier .env avec au moins 32 caractères.');
    }
    return $secret;
}

function auth_base64url_encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }

function auth_create_token(array $user): string
{
    $now = time();
    $header = auth_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
    $payload = auth_base64url_encode(json_encode(['sub' => (int) $user['Id_users'], 'name' => $user['name'], 'email' => $user['email'], 'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
    $signature = auth_base64url_encode(hash_hmac('sha256', $header . '.' . $payload, auth_jwt_secret(), true));
    return $header . '.' . $payload . '.' . $signature;
}

function auth_decode_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $signature] = $parts;
    try {
        $headerData = json_decode(base64_decode(strtr($header, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        $expected = auth_base64url_encode(hash_hmac('sha256', $header . '.' . $payload, auth_jwt_secret(), true));
        if (($headerData['alg'] ?? '') !== 'HS256' || !hash_equals($expected, $signature) || !is_array($claims) || (int) ($claims['exp'] ?? 0) <= time() || empty($claims['sub'])) return null;
        return $claims;
    } catch (Throwable $exception) {
        return null;
    }
}

function auth_user_from_cookie(): ?array
{
    $token = $_COOKIE['uniflow_token'] ?? '';
    if (!is_string($token) || $token === '') return null;
    $claims = auth_decode_token($token);
    if ($claims === null) return null;
    $query = db()->prepare('SELECT Id_users, name, email FROM Users WHERE Id_users = :id AND status = :status LIMIT 1');
    $query->execute(['id' => (int) $claims['sub'], 'status' => 'active']);
    return $query->fetch() ?: null;
}

function auth_set_cookie(array $user): void
{
    setcookie('uniflow_token', auth_create_token($user), ['expires' => time() + 3600, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
}

function auth_handle(string $action, string $name, string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) throw new RuntimeException('Veuillez saisir une adresse e-mail valide.');
    if ($password === '' || strlen($password) > 4096) throw new RuntimeException('Veuillez saisir un mot de passe valide.');
    $pdo = db();
    if ($action === 'register') {
        $name = trim($name);
        if ($name === '' || strlen($name) > 255) throw new RuntimeException('Veuillez saisir votre nom complet (255 caractères maximum).');
        if (strlen($password) < 8) throw new RuntimeException('Votre mot de passe doit contenir au moins 8 caractères.');
        $check = $pdo->prepare('SELECT Id_users FROM Users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetch()) throw new RuntimeException('Cette adresse e-mail possède déjà un compte.');
        $insert = $pdo->prepare('INSERT INTO Users (name, email, password, role, status) VALUES (:name, :email, :password, :role, :status)');
        $insert->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'student', 'status' => 'active']);
        return ['Id_users' => (int) $pdo->lastInsertId(), 'name' => $name, 'email' => $email];
    }
    $query = $pdo->prepare('SELECT Id_users, name, email, password FROM Users WHERE email = :email AND status = :status LIMIT 1');
    $query->execute(['email' => $email, 'status' => 'active']);
    $user = $query->fetch();
    if (!$user || !password_verify($password, $user['password'])) throw new RuntimeException('Adresse e-mail ou mot de passe incorrect.');
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $update = $pdo->prepare('UPDATE Users SET password = :password WHERE Id_users = :id');
        $update->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['Id_users']]);
    }
    unset($user['password']);
    return $user;
}
