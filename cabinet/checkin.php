<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$userId = $_SESSION['user']['id'];
$today  = date('Y-m-d');
$now    = date('H:i:s');

/* ======================
   ⚙️ НАСТРОЙКИ
====================== */
$settings = $pdo->query("
    SELECT 
        start_time,
        allowed_late_minutes,
        block_after_minutes,
        allow_manual
    FROM attendance_settings
    WHERE id = 1
")->fetch();

$startTime        = $settings['start_time'] ?? '09:00:00';
$allowedLate      = (int)($settings['allowed_late_minutes'] ?? 0);
$blockAfter       = (int)($settings['block_after_minutes'] ?? 0);
$allowManual      = (int)($settings['allow_manual'] ?? 0);

/* ======================
   ⏰ РАСЧЁТ ОПОЗДАНИЯ
====================== */
$startTs = strtotime("$today $startTime");
$nowTs   = strtotime("$today $now");

$lateMinutes = 0;
if ($nowTs > $startTs) {
    $lateMinutes = floor(($nowTs - $startTs) / 60);
}

/* ======================
   🔎 ПРОВЕРКА СМЕНЫ
====================== */
$stmt = $pdo->prepare("
    SELECT ws.branch_id
    FROM work_schedule ws
    WHERE ws.user_id = ?
      AND ws.work_date = ?
");
$stmt->execute([$userId, $today]);
$shift = $stmt->fetch();

$hasShift = (bool)$shift;

/* ======================
   🔎 УЖЕ ОТМЕЧАЛСЯ?
====================== */
$stmt = $pdo->prepare("
    SELECT 1 FROM work_checkins
    WHERE user_id = ? AND work_date = ?
");
$stmt->execute([$userId, $today]);
$alreadyChecked = (bool)$stmt->fetch();

/* =====================================================
   🟡 РЕЖИМ STATUS — ДЛЯ КНОПКИ (AJAX ?status=1)
===================================================== */
if (isset($_GET['status'])) {

    if ($alreadyChecked) {
        echo json_encode([
            'state' => 'red',
            'text'  => '✅ Уже отмечено'
        ]);
        exit;
    }

    if (!$hasShift && !$allowManual) {
        echo json_encode([
            'state' => 'red',
            'text'  => '❌ Нет смены'
        ]);
        exit;
    }

    if ($lateMinutes > $blockAfter) {
        echo json_encode([
            'state' => 'red',
            'text'  => '❌ Просрочено'
        ]);
        exit;
    }

    if ($lateMinutes > $allowedLate) {
        echo json_encode([
            'state' => 'yellow',
            'text'  => '⏰ Опоздание'
        ]);
        exit;
    }

    echo json_encode([
        'state' => 'green',
        'text'  => '🟢 Отметиться'
    ]);
    exit;
}

/* =====================================================
   🟢 САМА ОТМЕТКА (POST)
===================================================== */

// уже отмечался
if ($alreadyChecked) {
    http_response_code(409);
    exit('Вы уже отметились сегодня');
}

// нет смены
if (!$hasShift && !$allowManual) {
    http_response_code(403);
    exit('Сегодня у вас нет смены');
}

// слишком поздно
if ($lateMinutes > $blockAfter) {
    http_response_code(403);
    exit('Отметка запрещена — слишком поздно');
}

/* ======================
   💾 СОХРАНЯЕМ ОТМЕТКУ
====================== */
$stmt = $pdo->prepare("
    INSERT INTO work_checkins
    (user_id, branch_id, work_date, checkin_time, late_minutes)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $userId,
    $shift['branch_id'] ?? null,
    $today,
    $now,
    $lateMinutes
]);

echo 'OK';