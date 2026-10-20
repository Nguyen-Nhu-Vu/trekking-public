
<?php
// config/database.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'trekking_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD');
$password = $password === false ? '' : $password;

$isOnline = getenv('DB_HOST') !== false;

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10
    ];

    if ($isOnline) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        $options
    );

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu.');
}

if (!defined('BASE_URL')) {
    $base = getenv('BASE_URL');
    define('BASE_URL', $base !== false && $base !== ''
        ? rtrim($base, '/') . '/'
        : '/trekking/');
}
