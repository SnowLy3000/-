<?php
// /cron/remind_tomorrow_shift.php
require __DIR__ . '/../includes/db.php';

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
$y = (int)date('Y', strtotime($tomorrow));
$m = (int)date('n', strtotime($tomorrow));

/**
 * Берём всех пользователей у кого есть смена завтра.
 * (один сотрудник может иметь несколько записей в день — тогда просто одно уведомление)
 */
$stmt = $pdo->prepare("
    SELECT DISTINCT ws.user_id,
           GROUP_CONCAT(DISTINCT COALESCE(b.title,'—') ORDER BY b.title SEPARATOR ', ') AS branches
    FROM work_schedule ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.work_date = ?
    GROUP BY ws.user_id
");
$stmt->execute([$tomorrow]);
$rows = $stmt->fetchAll();

if (!$rows) exit;

// ссылка ведёт на календарь сотрудника
$link = "/cabinet/calendar.php?year={$y}&month={$m}";

foreach ($rows as $r) {
    $userId = (int)$r['user_id'];
    $branches = (string)($r['branches'] ?? '—');

    $title = "Завтра смена";
    $body  = "Дата: {$tomorrow}\nФилиал(ы): {$branches}";

    // анти-дубликат: если за сегодня уже создавали такое же
    $chk = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
          AND title = ?
          AND link = ?
          AND DATE(created_at) = CURDATE()
    ");
    $chk->execute([$userId, $title, $link]);
    if ((int)$chk->fetchColumn() > 0) continue;

    $ins = $pdo->prepare("
        INSERT INTO notifications (user_id, title, body, link, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $ins->execute([$userId, $title, $body, $link]);
}