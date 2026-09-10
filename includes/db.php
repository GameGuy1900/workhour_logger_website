<?php

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die('Missing config.php. Copy config.sample.php to config.php and fill in your database details.');
}
require_once $configPath;

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
