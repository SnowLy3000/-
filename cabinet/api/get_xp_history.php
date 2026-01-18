<?php
// cabinet/api/get_xp_history.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Проверка авторизации
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$userId = $_SESSION['user']['id'];

try {
    // Выбираем историю опыта из таблицы логов
    $stmt = $pdo->prepare("
        SELECT amount, reason, DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') as date 
        FROM user_xp_log 
        WHERE user_id = ? 
        ORDER BY id DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($history);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}