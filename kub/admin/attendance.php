<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('CHECKIN_VIEW');

/* =====================
   📅 ФИЛЬТРЫ
===================== */
$date     = $_GET['date'] ?? date('Y-m-d');
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
   🟢 ОТМЕТКИ
===================== */
$sql = "
    SELECT
        u.fullname,
        b.title AS branch,
        wc.work_date,
        wc.checkin_time,
        wc.late_minutes
    FROM work_checkins wc
    JOIN users u ON u.id = wc.user_id
    JOIN branches b ON b.id = wc.branch_id
    WHERE wc.work_date = ?
";

$params = [$date];

if ($branchId) {
    $sql .= " AND wc.branch_id = ?";
    $params[] = $branchId;
}

if ($userId) {
    $sql .= " AND wc.user_id = ?";
    $params[] = $userId;
}

$sql .= " ORDER BY wc.checkin_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* =====================
   📊 ИТОГИ
===================== */
$total = count($rows);
$lateCount = 0;

foreach ($rows as $r) {
    if ($r['late_minutes'] > 0) {
        $lateCount++;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Отметки прихода</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:10px;
    border-bottom:1px solid rgba(255,255,255,.1);
}
th{
    text-align:left;
    opacity:.7;
}
.late{
    color:#ff6b6b;
    font-weight:bold;
}
.ok{
    color:#2ecc71;
    font-weight:bold;
}
.summary{
    display:flex;
    gap:20px;
    margin:20px 0;
}
.card{
    padding:14px;
    border-radius:10px;
    background:#1e1e2a;
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
</aside>

<main class="admin-main">

<h1>🟢 Отметки прихода</h1>

<form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">

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

<div class="summary">
    <div class="card">👥 Отметились: <b><?= $total ?></b></div>
    <div class="card">⏰ Опоздали: <b><?= $lateCount ?></b></div>
</div>

<table>
<tr>
    <th>Сотрудник</th>
    <th>Филиал</th>
    <th>Дата</th>
    <th>Время</th>
    <th>Статус</th>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['fullname']) ?></td>
    <td><?= htmlspecialchars($r['branch']) ?></td>
    <td><?= htmlspecialchars($r['work_date']) ?></td>
    <td><?= htmlspecialchars(substr($r['checkin_time'],0,5)) ?></td>
    <td>
        <?php if ($r['late_minutes'] > 0): ?>
            <span class="late">⏰ <?= (int)$r['late_minutes'] ?> мин</span>
        <?php else: ?>
            <span class="ok">✓ Вовремя</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

</main>
</div>

</body>
</html>