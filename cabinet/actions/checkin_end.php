<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

$shiftId = (int)($_POST['shift_id'] ?? 0);
if (!$shiftId) {
    exit('Invalid shift');
}

// фиксируем окончание смены
$pdo->prepare("
    UPDATE checkins
    SET checkout_time = NOW()
    WHERE shift_id = ?
")->execute([$shiftId]);

// закрываем смену
$pdo->prepare("
    UPDATE work_shifts
    SET status = 'completed'
    WHERE id = ?
")->execute([$shiftId]);

header('Location: /cabinet/index.php?page=checkin');
exit;