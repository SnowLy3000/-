<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
require_role('Admin');

$date = $_GET['date'] ?? '';
$branchId = (int)($_GET['branch_id'] ?? 0);

if (!$date || !$branchId) {
    echo '<div class="muted">Нет данных</div>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT ws.id, u.first_name, u.last_name
    FROM work_shifts ws
    JOIN users u ON u.id = ws.user_id
    WHERE ws.shift_date = ?
      AND ws.branch_id = ?
    ORDER BY u.last_name, u.first_name
");
$stmt->execute([$date, $branchId]);
$rows = $stmt->fetchAll();

if (!$rows) {
    echo '<div class="muted">Никто не назначен</div>';
    exit;
}

foreach ($rows as $r) {
    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin:4px 0;">';
    echo '<span>'.htmlspecialchars($r['last_name'].' '.$r['first_name']).'</span>';
    echo '<button class="btn btn-danger btn-sm"
            data-shift="'.$r['id'].'">✖</button>';
    echo '</div>';
}