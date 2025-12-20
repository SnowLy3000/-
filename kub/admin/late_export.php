<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('LATE_VIEW');

/* =====================
   📅 ФИЛЬТРЫ
===================== */
$month    = $_GET['month'] ?? date('Y-m');
$branchId = (int)($_GET['branch_id'] ?? 0);
$userId   = (int)($_GET['user_id'] ?? 0);

/* =====================
   📤 CSV HEADERS
===================== */
$filename = "late_stats_$month.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// BOM для Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

/* =====================
   CSV ШАПКА
===================== */
fputcsv($out, [
    'Сотрудник',
    'Филиал',
    'Дата',
    'Минут опоздания',
    'Штраф'
], ';');

/* =====================
   📊 ДАННЫЕ
===================== */
$sql = "
    SELECT 
        u.fullname,
        b.title AS branch,
        wc.work_date,
        wc.late_minutes,
        COALESCE(lp.amount, 0) AS penalty_amount
    FROM work_checkins wc
    JOIN users u ON u.id = wc.user_id
    JOIN branches b ON b.id = wc.branch_id
    LEFT JOIN late_penalties lp
        ON lp.user_id = wc.user_id
       AND lp.work_date = wc.work_date
    WHERE wc.late_minutes > 0
      AND DATE_FORMAT(wc.work_date,'%Y-%m') = ?
";

$params = [$month];

if ($branchId) {
    $sql .= " AND wc.branch_id = ?";
    $params[] = $branchId;
}

if ($userId) {
    $sql .= " AND wc.user_id = ?";
    $params[] = $userId;
}

$sql .= " ORDER BY wc.work_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

foreach ($stmt->fetchAll() as $r) {
    fputcsv($out, [
        $r['fullname'],
        $r['branch'],
        $r['work_date'],
        $r['late_minutes'],
        number_format((float)$r['penalty_amount'], 2, '.', '')
    ], ';');
}

fclose($out);
exit;