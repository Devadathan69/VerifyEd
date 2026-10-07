<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('VerifyED is not configured. Copy config.example.php to config.php, then import schema.sql.');
}

$config = require $configPath;

if (!is_array($config)) {
    http_response_code(500);
    exit('VerifyED configuration is invalid.');
}

// Keep session identifiers out of scripts, cross-site form posts, and stale sessions.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('display_errors', !empty($config['app_debug']) ? '1' : '0');
ini_set('log_errors', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool) ($config['session_secure'] ?? $isHttps),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'");
if ((bool) ($config['session_secure'] ?? $isHttps)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('VerifyED could not connect to MySQL. Check config.php and make sure MySQL is running.');
}

require_once __DIR__ . '/functions.php';
