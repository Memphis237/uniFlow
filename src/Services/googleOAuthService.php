<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/authService.php';

function google_oauth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function google_oauth_config(): array
{
    $clientId = trim((string) app_env('GOOGLE_CLIENT_ID', ''));
    $clientSecret = trim((string) app_env('GOOGLE_CLIENT_SECRET', ''));
    $redirectUri = trim((string) app_env('GOOGLE_REDIRECT_URI', ''));
    if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
        throw new RuntimeException('La connexion Google n’est pas encore configurée.');
    }
    $uri = parse_url($redirectUri);
    $scheme = strtolower((string)($uri['scheme'] ?? ''));
    $isLocalHttp = in_array(strtolower((string)($uri['host'] ?? '')), ['localhost', '127.0.0.1'], true) && $scheme === 'http';
    if (!is_array($uri) || !in_array($scheme, ['https', 'http'], true)
        || ($scheme !== 'https' && !$isLocalHttp) || empty($uri['host'])) {
        throw new RuntimeException('L’URL de retour Google doit utiliser HTTPS, sauf en développement local.');
    }
    return ['client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirectUri];
}

function google_oauth_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function google_oauth_start_url(): string
{
    $config = google_oauth_config();
    google_oauth_session_start();

    $state = google_oauth_base64url(random_bytes(32));
    $verifier = google_oauth_base64url(random_bytes(64));
    $_SESSION['google_oauth'] = [
        'state' => $state,
        'verifier' => $verifier,
        'created_at' => time(),
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'code_challenge' => google_oauth_base64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ], '', '&', PHP_QUERY_RFC3986);
}

function google_oauth_http(string $url, string $method, array $headers, ?string $body = null): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $response = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if (!is_string($response) || !preg_match('/\s2\d\d\s/', $statusLine)) {
        throw new RuntimeException('Google n’a pas pu confirmer la connexion. Réessaie.');
    }
    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Réponse Google invalide. Réessaie.');
    }
    if (!is_array($data)) throw new RuntimeException('Réponse Google invalide. Réessaie.');
    return $data;
}

function google_oauth_ensure_schema(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM Users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('google_sub', $columns, true)) {
        $pdo->exec('ALTER TABLE Users ADD COLUMN google_sub VARCHAR(255) NULL');
    }
    $indexes = $pdo->query('SHOW INDEX FROM Users')->fetchAll(PDO::FETCH_ASSOC);
    $hasGoogleUnique = false;
    foreach ($indexes as $index) {
        if (($index['Key_name'] ?? '') === 'uq_users_google_sub' && (int)($index['Non_unique'] ?? 1) === 0) {
            $hasGoogleUnique = true;
            break;
        }
    }
    if (!$hasGoogleUnique) {
        try {
            $pdo->exec('ALTER TABLE Users ADD UNIQUE KEY uq_users_google_sub (google_sub)');
        } catch (PDOException $exception) {
            throw new RuntimeException('La base UniFlow doit appliquer src/database/migrations/20261110_google_oauth.sql avec un compte ayant le droit ALTER TABLE.', 0, $exception);
        }
    }
}

function google_oauth_user(array $profile): array
{
    $googleSub = trim((string)($profile['sub'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $emailVerified = ($profile['email_verified'] ?? false) === true || ($profile['email_verified'] ?? '') === 'true';
    if ($googleSub === '' || strlen($googleSub) > 255 || !$emailVerified || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        throw new RuntimeException('Google doit fournir une adresse e-mail vérifiée pour créer le compte.');
    }

    $pdo = db();
    google_oauth_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT Id_users,name,first_name,email,role,status,google_sub FROM Users WHERE google_sub=:sub LIMIT 1');
        $find->execute(['sub' => $googleSub]);
        $user = $find->fetch();
        if ($user) {
            if (($user['status'] ?? '') !== 'active') throw new RuntimeException('Ce compte UniFlow est désactivé.');
            $pdo->commit();
            return $user;
        }

        $find = $pdo->prepare('SELECT Id_users,name,first_name,email,role,status,google_sub FROM Users WHERE email=:email LIMIT 1');
        $find->execute(['email' => $email]);
        $user = $find->fetch();
        $picture = (string)($profile['picture'] ?? '');
        if (!filter_var($picture, FILTER_VALIDATE_URL) || parse_url($picture, PHP_URL_SCHEME) !== 'https') $picture = '';

        if ($user) {
            if (($user['status'] ?? '') !== 'active') throw new RuntimeException('Un compte associé à cette adresse est désactivé.');
            if (!empty($user['google_sub'])) throw new RuntimeException('Cette adresse est déjà associée à un autre compte Google.');
            $update = $pdo->prepare('UPDATE Users SET google_sub=:sub,avatar=CASE WHEN (avatar IS NULL OR avatar=\'\') AND :picture<>\'\' THEN :picture2 ELSE avatar END WHERE Id_users=:id');
            $update->execute(['sub' => $googleSub, 'picture' => $picture, 'picture2' => $picture, 'id' => (int)$user['Id_users']]);
        } else {
            $fullName = trim((string)($profile['name'] ?? ''));
            $firstName = trim((string)($profile['given_name'] ?? ''));
            $familyName = trim((string)($profile['family_name'] ?? ''));
            if ($firstName === '') {
                $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
                $firstName = trim((string)($nameParts[0] ?? ''));
                if ($familyName === '') $familyName = trim((string)($nameParts[1] ?? ''));
            }
            if ($firstName === '') $firstName = 'Étudiant';
            if ($familyName === '') $familyName = 'UniFlow';
            $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO Users(name,first_name,email,password,role,status,avatar,google_sub) VALUES(:name,:first_name,:email,:password,\'student\',\'active\',:avatar,:sub)');
            $insert->execute([
                'name' => mb_substr($familyName, 0, 255),
                'first_name' => mb_substr($firstName, 0, 255),
                'email' => $email,
                'password' => $passwordHash,
                'avatar' => $picture !== '' ? $picture : null,
                'sub' => $googleSub,
            ]);
            $user = ['Id_users' => (int)$pdo->lastInsertId(), 'name' => $familyName, 'first_name' => $firstName, 'email' => $email, 'role' => 'student', 'status' => 'active'];
        }
        $pdo->commit();
        $user['google_sub'] = $googleSub;
        return $user;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function google_oauth_complete(array $query): array
{
    google_oauth_session_start();
    $pending = $_SESSION['google_oauth'] ?? null;
    unset($_SESSION['google_oauth']);
    if (!is_array($pending) || !isset($pending['state'], $pending['verifier'], $pending['created_at'])
        || time() - (int)$pending['created_at'] > 600 || !isset($query['state'])
        || !is_string($query['state']) || !hash_equals((string)$pending['state'], $query['state'])) {
        throw new RuntimeException('La vérification de sécurité Google a échoué. Recommence la connexion.');
    }
    if (!empty($query['error'])) throw new RuntimeException('La connexion Google a été annulée.');
    $code = $query['code'] ?? '';
    if (!is_string($code) || $code === '' || strlen($code) > 4096) throw new RuntimeException('Code Google manquant. Recommence la connexion.');

    $config = google_oauth_config();
    $tokens = google_oauth_http('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
    ], http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
        'code_verifier' => (string)$pending['verifier'],
    ], '', '&', PHP_QUERY_RFC3986));
    $accessToken = $tokens['access_token'] ?? '';
    if (!is_string($accessToken) || $accessToken === '') throw new RuntimeException('Google n’a pas retourné de jeton de connexion valide.');
    $profile = google_oauth_http('https://openidconnect.googleapis.com/v1/userinfo', 'GET', [
        'Authorization' => 'Bearer ' . $accessToken,
        'Accept' => 'application/json',
    ]);
    $user = google_oauth_user($profile);
    session_regenerate_id(true);
    return $user;
}
