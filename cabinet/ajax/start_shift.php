<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$branchId = (int)($_POST['branch_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'message'=>'Не авторизован.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($branchId <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Не выбран филиал.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)setting_get_int($pdo, 'checkin_enabled', 1) !== 1) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Check-in отключён настройками.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

try {
    // проверим: уже есть смена сегодня?
    $stmt = $pdo->prepare("SELECT id FROM shift_sessions WHERE user_id=? AND shift_date=? LIMIT 1");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'message'=>'Вы уже сделали check-in сегодня.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // создаём смену
    $stmt = $pdo->prepare("
        INSERT INTO shift_sessions (user_id, branch_id, shift_date, checkin_at)
        VALUES (?,?,?,?)
    ");
    $stmt->execute([$userId, $branchId, $today, $now]);

    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Ошибка сервера.'], JSON_UNESCAPED_UNICODE);
}
