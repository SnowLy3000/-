<?php
// /home/oizsopkxtv/kubinfo.live/admin/cron_close_shifts.php

// 1. Указываем абсолютный путь к конфигу и БД
$root = '/home/oizsopkxtv/kubinfo.live'; 
require_once $root . '/includes/db.php';

// Берем дату из конфига (она уже там задана, как ты показал)
$today = date('Y-m-d');

try {
    // Закрываем смены за ПРОШЛЫЕ дни
    $stmt = $pdo->prepare("
        UPDATE shift_sessions
        SET checkout_at = CONCAT(shift_date, ' 23:59:59')
        WHERE checkout_at IS NULL
          AND shift_date < ?
    ");
    $stmt->execute([$today]);
    $closedPast = $stmt->rowCount();

    // ДОПОЛНИТЕЛЬНО: Закрываем смены за СЕГОДНЯ, если они висят слишком долго (например, > 16 часов)
    // Это на случай, если кто-то забыл выйти утром и смена висит весь день
    $stmt = $pdo->prepare("
        UPDATE shift_sessions
        SET checkout_at = NOW()
        WHERE checkout_at IS NULL
          AND shift_date = ?
          AND checkin_at < (NOW() - INTERVAL 16 HOUR)
    ");
    $stmt->execute([$today]);
    $closedToday = $stmt->rowCount();

    echo "Cron log [" . date('Y-m-d H:i:s') . "]: Closed past: $closedPast, Closed long-running today: $closedToday\n";

} catch (Exception $e) {
    echo "Cron error: " . $e->getMessage();
}
