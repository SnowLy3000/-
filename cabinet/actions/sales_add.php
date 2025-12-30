<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();

header('Content-Type: application/json');

$userId = $_SESSION['user']['id'] ?? 0;

/**
 * 🔒 Проверка check-in
 */
$stmt = $pdo->prepare("
    SELECT branch_id
    FROM shift_sessions
    WHERE user_id = ?
      AND checkout_at IS NULL
    ORDER BY checkin_at DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Перед добавлением продажи необходимо начать смену'
    ]);
    exit;
}

$branchId = (int)$session['branch_id'];

/**
 * 🧾 Создаём чек
 */
$stmt = $pdo->prepare("
    INSERT INTO sales (user_id, branch_id, payment_type, total_amount)
    VALUES (?, ?, 'cash', 0.00)
");
$stmt->execute([$userId, $branchId]);

echo json_encode([
    'ok' => true,
    'sale_id' => $pdo->lastInsertId()
]);
exit;