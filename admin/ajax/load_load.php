<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
require_role('Admin');

$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
$branchId = (int)($_GET['branch_id'] ?? 0);

if ($m < 1) $m = 1;
if ($m > 12) $m = 12;

$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

function groupLoad(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $cnt = (int)$r['cnt'];
        $out[$cnt][] = trim($r['last_name'].' '.$r['first_name']);
    }
    return $out;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Общая загруженность
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, COUNT(ws.id) AS cnt
    FROM users u
    LEFT JOIN work_shifts ws
        ON ws.user_id = u.id
       AND ws.shift_date BETWEEN ? AND ?
    WHERE u.status = 'active'
    GROUP BY u.id
    HAVING cnt > 0
    ORDER BY cnt DESC
");
$stmt->execute([$monthStart, $monthEnd]);
$overallGrouped = groupLoad($stmt->fetchAll());

// Загруженность филиала (если выбран)
$branchGrouped = [];
$branchName = '';

if ($branchId > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, COUNT(ws.id) AS cnt
        FROM users u
        JOIN work_shifts ws ON ws.user_id = u.id
        WHERE ws.branch_id = ?
          AND ws.shift_date BETWEEN ? AND ?
          AND u.status = 'active'
        GROUP BY u.id
        HAVING cnt > 0
        ORDER BY cnt DESC
    ");
    $stmt->execute([$branchId, $monthStart, $monthEnd]);
    $branchGrouped = groupLoad($stmt->fetchAll());

    $bn = $pdo->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
    $bn->execute([$branchId]);
    $branchName = (string)$bn->fetchColumn();
}
?>

<?php if ($branchId > 0): ?>
<div class="card">
    <h3>Загруженность филиала: <?= h($branchName) ?></h3>
    <?php if (!$branchGrouped): ?>
        <div class="muted">Нет смен за месяц.</div>
    <?php else: ?>
        <?php foreach ($branchGrouped as $cnt => $names): ?>
            <div style="margin:6px 0;">
                <b><?= (int)$cnt ?> смен</b> — <?= h(implode(', ', $names)) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3>Общая загруженность (все филиалы)</h3>
    <?php if (!$overallGrouped): ?>
        <div class="muted">Нет смен за месяц.</div>
    <?php else: ?>
        <?php foreach ($overallGrouped as $cnt => $names): ?>
            <div style="margin:6px 0;">
                <b><?= (int)$cnt ?> смен</b> — <?= h(implode(', ', $names)) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>