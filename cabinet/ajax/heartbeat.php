<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

if ($userId > 0) {
    // 1. Обновляем время активности в таблице пользователей
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    // 2. Записываем сигнал в лог
    $stmtLog = $pdo->prepare("INSERT INTO user_sessions_log (user_id, action_type, ip_address) VALUES (?, 'ping', ?)");
    $stmtLog->execute([$userId, $_SERVER['REMOTE_ADDR']]);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
}
