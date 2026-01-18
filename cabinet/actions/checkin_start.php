<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)$_SESSION['user']['id'];
$branchId = (int)$_POST['branch_id'];
$today = date('Y-m-d');

if (!$branchId) exit('Филиал не определен');

// 1. ПОЛУЧАЕМ ГРАФИК ОТКРЫТИЯ ЭТОГО ФИЛИАЛА
$stmt = $pdo->prepare("
    SELECT opening_time 
    FROM branch_schedules 
    WHERE branch_id = ? 
    LIMIT 1
");
$stmt->execute([$branchId]);
$openingTime = $stmt->fetchColumn() ?: '09:00:00'; // Если нет в спец. таблице, берем 09:00

// 2. ВЫЧИСЛЯЕМ ОПОЗДАНИЕ
$now = new DateTime(); // Текущее время (время нажатия кнопки)
$scheduled = new DateTime(date('Y-m-d ') . $openingTime); // Время когда должен был быть на месте

$lateMinutes = 0;
// Если текущее время больше запланированного
if ($now > $scheduled) {
    $diff = $now->diff($scheduled);
    // Считаем общие минуты (часы * 60 + минуты)
    $lateMinutes = ($diff->h * 60) + $diff->i;
}

// 3. СОЗДАЕМ СЕССИЮ СМЕНЫ
$stmt = $pdo->prepare("
    INSERT INTO shift_sessions (
        user_id, 
        branch_id, 
        shift_date, 
        checkin_at, 
        late_minutes
    ) VALUES (?, ?, ?, NOW(), ?)
");

try {
    $stmt->execute([$userId, $branchId, $today, $lateMinutes]);
    header("Location: /cabinet/index.php?page=checkin");
} catch (Exception $e) {
    exit("Ошибка: Смена на сегодня уже открыта или ошибка БД: " . $e->getMessage());
}
