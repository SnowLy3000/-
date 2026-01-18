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
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET time_zone = '+02:00'");
} catch (PDOException $e) {
    // В случае ошибки можно логировать или выводить сообщение
    // die("Ошибка подключения: " . $e->getMessage());
}

/**
 * Функция для получения пути к аватарке пользователя
 * @param array $user — массив с данными пользователя (должен содержать id и avatar)
 */
function get_user_avatar($user) {
    // Если в базе прописан путь к загруженному файлу и он физически существует
    if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])) {
        return '/' . $user['avatar'];
    }
    
    // Иначе используем стандартную аватарку 1.png ... 15.png
    // Вычисляем номер на основе ID пользователя, чтобы аватарка была постоянной
    $userId = isset($user['user_id']) ? $user['user_id'] : ($user['id'] ?? 0);
    $num = ($userId % 9) + 1;
    
    return "/assets/img/avatars/{$num}.png";
}