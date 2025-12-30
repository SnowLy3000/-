<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

$branchId  = (int)($_POST['branch_id'] ?? 0);
$shiftDate = $_POST['shift_date'] ?? date('Y-m-d');

if (!$branchId) {
    echo json_encode(['error' => 'Не выбран филиал']);
    exit;
}

/**
 * ❌ Запрет: уже есть активная смена сегодня
 */
$stmt = $pdo->prepare("
    SELECT id
    FROM shift_sessions
    WHERE user_id = ?
      AND shift_date = ?
      AND checkout_at IS NULL
    LIMIT 1
");
$stmt->execute([$user['id'], $shiftDate]);

if ($stmt->fetch()) {
    echo json_encode(['error' => 'У вас уже есть активная смена']);
    exit;
}

/**
 * ✅ Создаём check-in
 */
$stmt = $pdo->prepare("
    INSERT INTO shift_sessions
    (user_id, branch_id, shift_date, checkin_at, created_at)
    VALUES (?, ?, ?, NOW(), NOW())
");
$stmt->execute([
    $user['id'],
    $branchId,
    $shiftDate
]);

echo json_encode(['ok' => true]);
exit;