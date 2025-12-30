<?php
/**
 * Подключение к базе данных
 * + синхронизация таймзоны MySQL с PHP
 */

$config = require __DIR__ . '/config.php';

$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['name'],
    $db['charset']
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);

    // 🔥 КРИТИЧЕСКИ ВАЖНО — таймзона MySQL
    $pdo->exec("SET time_zone = '+02:00'");

} catch (PDOException $e) {
    http_response_code(500);
    exit('DB Connection failed: ' . $e->getMessage());
}