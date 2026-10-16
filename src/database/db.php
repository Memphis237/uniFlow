<?php
declare(strict_types=1);

/** Charge les variables du fichier .env sans dépendance externe. */
function app_env(string $key, ?string $default = null): ?string
{
    static $values;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file) && is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$name, $value] = explode('=', $line, 2);
                $values[trim($name)] = trim(trim($value), "\"'");
            }
        }
    }
    $serverValue = getenv($key);
    return $serverValue !== false ? $serverValue : ($values[$key] ?? $default);
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $host = app_env('DB_HOST', 'localhost');
    $port = app_env('DB_PORT', '3306');
    $name = app_env('DB_NAME', 'uniflow');
    $user = app_env('DB_USER', 'root');
    $password = app_env('DB_PASSWORD', '');
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
