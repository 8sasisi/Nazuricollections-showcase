<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . DIRECTORY_SEPARATOR . '..';

function load_environment(): void
{
    $envFile = getenv('APP_CONFIG_FILE');
    if ($envFile === false || $envFile === '') {
        return;
    }
    $realFile = realpath($envFile);
    $realRoot = realpath(APP_ROOT);
    if ($realFile === false || $realRoot === false || !is_file($realFile) || !is_readable($realFile)
        || str_starts_with(strtolower($realFile), strtolower($realRoot . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('The private environment file is missing or is located inside the public project directory.');
    }

    foreach (file($realFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . trim($value, "\"'"));
        }
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = $now;
}

function app_log(string $message): void
{
    $directory = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    error_log('[' . gmdate('c') . '] ' . $message . PHP_EOL, 3, $directory . DIRECTORY_SEPARATOR . 'application.log');
}

function remove_stored_image(string $filename): void
{
    if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $filename)) {
        throw new RuntimeException('Invalid stored image reference.');
    }
    $path = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        app_log('Unable to remove media file: ' . $filename);
    }
}

function database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST');
    $name = env_value('DB_NAME');
    $user = env_value('DB_USER');
    $password = env_value('DB_PASSWORD');
    if (!$host || !$name || !$user || $password === null) {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        env_value('DB_PORT', '3306'),
        $name
    );
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);
    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function escape_html(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        http_response_code(405);
        exit('Method not allowed.');
    }
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDirectory = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0700, true);
}
ini_set('error_log', $logDirectory . DIRECTORY_SEPARATOR . 'php-errors.log');
load_environment();
date_default_timezone_set('UTC');
if (env_value('APP_ENV') === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self' https://wa.me; frame-ancestors 'none'; object-src 'none'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'");
}
