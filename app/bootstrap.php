<?php
declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
$configPath = $root . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('VerifyED is not configured. Copy config.example.php to config.php, then import schema.sql.');
}

$config = require $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('VerifyED could not connect to MySQL. Check config.php and make sure MySQL is running.');
}

require_once __DIR__ . '/functions.php';
