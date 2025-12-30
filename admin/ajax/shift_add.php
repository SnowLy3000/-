<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$userId   = (int)$_POST['user_id'];
$branchId = (int)$_POST['branch_id'];
$date     = $_POST['date'];

// 1. ПРОВЕРКА: Не работает ли он уже в другом месте в этот день?
$stmt = $pdo->prepare("
    SELECT b.name 
    FROM work_shifts ws 
    JOIN branches b ON b.id = ws.branch_id 
    WHERE ws.user_id = ? AND ws.shift_date = ? AND ws.branch_id != ?
");
$stmt->execute([$userId, $date, $branchId]);
$otherBranch = $stmt->fetchColumn();

if ($otherBranch) {
    echo json_encode([
        'ok' => false, 
        'message' => "⛔ Конфликт! Этот сотрудник уже работает в филиале '$otherBranch' в этот день."
    ]);
    exit;
}

// 2. Если конфликтов нет — добавляем смену
try {
    $stmt = $pdo->prepare("INSERT INTO work_shifts (user_id, branch_id, shift_date) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $branchId, $date]);
    $newId = $pdo->lastInsertId();

    // Получаем имя для вставки в JS
    $u = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $u->execute([$userId]);
    $user = $u->fetch();

    echo json_encode([
        'ok' => true,
        'id' => $newId,
        'name' => $user['last_name'] . ' ' . mb_substr($user['first_name'], 0, 1) . '.',
        'full_name' => $user['last_name'] . ' ' . $user['first_name']
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => 'Ошибка базы']);
}