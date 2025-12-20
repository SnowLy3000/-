<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('LATE_VIEW');

$canSeeMoney = user_has('PENALTY_MANAGE');

/* =====================
   📅 ФИЛЬТРЫ
===================== */
$month    = $_GET['month'] ?? date('Y-m');
$branchId = (int)($_GET['branch_id'] ?? 0);
$userId   = (int)($_GET['user_id'] ?? 0);

/* =====================
   🏬 ФИЛИАЛЫ
===================== */
$branches = $pdo->query("
    SELECT id, title
    FROM branches
    ORDER BY title
")->fetchAll();

/* =====================
   👥 СОТРУДНИКИ
===================== */
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'employee'
    ORDER BY fullname
")->fetchAll();

/* =====================
   📊 ДАННЫЕ
===================== */
$sql = "
    SELECT
        u.fullname,
        b.title AS branch,
        wc.work_date,
        wc.late_minutes,
        COALESCE(lp.amount, 0) AS amount
    FROM work_checkins wc
    JOIN users u ON u.id = wc.user_id
    JOIN branches b ON b.id = wc.branch_id
    LEFT JOIN late_penalties lp
        ON lp.user_id = wc.user_id
       AND lp.work_date = wc.work_date
    WHERE wc.late_minutes > 0
      AND DATE_FORMAT(wc.work_date, '%Y-%m') = ?
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
$rows = $stmt->fetchAll();

/* =====================
   📈 ИТОГИ
===================== */
$totalLate    = count($rows);
$totalMinutes = array_sum(array_column($rows, 'late_minutes'));
$totalAmount  = array_sum(array_column($rows, 'amount'));
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>⏰ Опоздания и штрафы</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.1)}
th{text-align:left;opacity:.7}
.summary{display:flex;gap:16px;margin:20px 0}
.card{padding:14px;border-radius:12px;background:#1e1e2a}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
</aside>

<main class="admin-main">

<h1>⏰ Опоздания и штрафы</h1>

<form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">

    <select name="branch_id">
        <option value="">Все филиалы</option>
        <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $b['id']==$branchId?'selected':'' ?>>
                <?= htmlspecialchars($b['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="user_id">
        <option value="">Все сотрудники</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $u['id']==$userId?'selected':'' ?>>
                <?= htmlspecialchars($u['fullname']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn">Показать</button>
</form>

<a class="btn"
   href="/admin/late_export.php?month=<?= urlencode($month) ?>&branch_id=<?= $branchId ?>&user_id=<?= $userId ?>">
    📤 Экспорт CSV
</a>

<div class="summary">
    <div class="card">⏰ Опозданий: <b><?= $totalLate ?></b></div>
    <div class="card">🕒 Минут: <b><?= $totalMinutes ?></b></div>
    <?php if ($canSeeMoney): ?>
        <div class="card">💸 Штрафы: <b><?= number_format($totalAmount,2) ?> лей</b></div>
    <?php endif; ?>
</div>

<table>
<tr>
    <th>Сотрудник</th>
    <th>Филиал</th>
    <th>Дата</th>
    <th>Минут</th>
    <?php if ($canSeeMoney): ?><th>Штраф</th><?php endif; ?>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['fullname']) ?></td>
    <td><?= htmlspecialchars($r['branch']) ?></td>
    <td><?= htmlspecialchars($r['work_date']) ?></td>
    <td><?= (int)$r['late_minutes'] ?></td>
    <?php if ($canSeeMoney): ?>
        <td><?= number_format((float)$r['amount'],2) ?> лей</td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>

<?php if (!$rows): ?>
<tr>
    <td colspan="<?= $canSeeMoney?5:4 ?>" style="opacity:.6">
        Нет данных за выбранный период
    </td>
</tr>
<?php endif; ?>
</table>

</main>
</div>

</body>
</html>