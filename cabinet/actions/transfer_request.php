<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

$branchId  = (int)($_POST['branch_id'] ?? 0);
$toUserId  = (int)($_POST['to_user_id'] ?? 0);
$shiftDate = trim($_POST['shift_date'] ?? '');

if (!$branchId || !$toUserId || !$shiftDate) {
    $_SESSION['error'] = 'Некорректный запрос';
    header('Location: /cabinet/index.php?page=checkin');
    exit;
}

if ($toUserId === (int)$user['id']) {
    $_SESSION['error'] = 'Нельзя передать смену самому себе';
    header('Location: /cabinet/index.php?page=checkin');
    exit;
}

// 1) есть ли активная сессия у отправителя именно на этот день/филиал
$stmt = $pdo->prepare("
    SELECT id, shift_date
    FROM shift_sessions
    WHERE user_id = ?
      AND branch_id = ?
      AND shift_date = ?
      AND checkout_at IS NULL
    ORDER BY checkin_at DESC
    LIMIT 1
");
$stmt->execute([$user['id'], $branchId, $shiftDate]);
$activeSession = $stmt->fetch();

if (!$activeSession) {
    $_SESSION['error'] = 'Нет активной смены для передачи';
    header('Location: /cabinet/index.php?page=checkin');
    exit;
}

// 2) не передавалась ли уже ЭТА смена (по дате+филиалу)
$stmt = $pdo->prepare("
    SELECT id
    FROM shift_transfers
    WHERE from_user_id = ?
      AND branch_id = ?
      AND shift_date = ?
      AND status IN ('pending','accepted')
    LIMIT 1
");
$stmt->execute([$user['id'], $branchId, $shiftDate]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'Эта смена уже передана или ожидает подтверждения';
    header('Location: /cabinet/index.php?page=checkin');
    exit;
}

// 3) создаём заявку
$stmt = $pdo->prepare("
    INSERT INTO shift_transfers
        (from_user_id, to_user_id, branch_id, shift_date, from_session_id, status, created_at)
    VALUES
        (?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->execute([
    $user['id'],
    $toUserId,
    $branchId,
    $shiftDate,
    (int)$activeSession['id'],
]);

// 4) уведомление получателю
$stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, type, message, created_at)
    VALUES (?, 'transfer', 'Вам передана смена', NOW())
");
$stmt->execute([$toUserId]);

$_SESSION['success'] = 'Заявка на передачу отправлена';
header('Location: /cabinet/index.php?page=checkin');
exit;