<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
require_role('Admin');

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo '<div class="muted">Некорректный сотрудник</div>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        ws.shift_date,
        b.name AS branch_name
    FROM work_shifts ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.user_id = ?
    ORDER BY ws.shift_date ASC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

if (!$rows) {
    echo '<div class="muted">Смен пока нет</div>';
    exit;
}

echo '<ul style="padding-left:16px;margin:0;">';
foreach ($rows as $r) {
    $date = date('d.m.Y', strtotime($r['shift_date']));
    echo '<li style="margin:4px 0;">📅 '.$date.' — '.htmlspecialchars($r['branch_name']).'</li>';
}
echo '</ul>';