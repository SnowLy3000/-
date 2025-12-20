<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$userId = $_SESSION['user']['id'];
$month = $_GET['month'] ?? date('Y-m');

/* =====================
   ⚙️ НАСТРОЙКИ
===================== */
$settings = $pdo->query("
    SELECT enable_penalties
    FROM attendance_settings
    WHERE id = 1
")->fetch();

$penaltiesEnabled = (int)($settings['enable_penalties'] ?? 0);

/* =====================
   ⏰ ОПОЗДАНИЯ
===================== */
$stmt = $pdo->prepare("
    SELECT 
        wc.work_date,
        wc.late_minutes,
        lp.amount
    FROM work_checkins wc
    LEFT JOIN late_penalties lp
        ON lp.user_id = wc.user_id
       AND lp.work_date = wc.work_date
    WHERE wc.user_id = ?
      AND wc.late_minutes > 0
      AND DATE_FORMAT(wc.work_date,'%Y-%m') = ?
    ORDER BY wc.work_date DESC
");
$stmt->execute([$userId, $month]);
$rows = $stmt->fetchAll();

/* =====================
   📊 ИТОГИ
===================== */
$totalLate = count($rows);
$totalMinutes = array_sum(array_column($rows, 'late_minutes'));
$totalAmount = array_sum(array_column($rows, 'amount'));
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Мои опоздания</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:10px;
    border-bottom:1px solid rgba(255,255,255,.1);
}
th{opacity:.7;text-align:left}
.card{
    padding:14px;
    border-radius:12px;
    background:#1e1e2a;
}
.summary{
    display:flex;
    gap:16px;
    margin:20px 0;
}
</style>
</head>
<body>

<div class="admin-wrap">
<aside class="admin-menu neon">
    <a href="/cabinet/index.php">← Назад</a>
</aside>

<main class="admin-main">

<h1>⏰ Мои опоздания</h1>

<form method="get" style="margin-bottom:15px">
    <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
    <button class="btn">Показать</button>
</form>

<div class="summary">
    <div class="card">⏰ Опозданий: <b><?= $totalLate ?></b></div>
    <div class="card">🕒 Минут: <b><?= $totalMinutes ?></b></div>
    <?php if ($penaltiesEnabled): ?>
        <div class="card">💸 Штраф: <b><?= number_format($totalAmount,2) ?> лей</b></div>
    <?php endif; ?>
</div>

<table>
<tr>
    <th>Дата</th>
    <th>Минут</th>
    <?php if ($penaltiesEnabled): ?>
        <th>Штраф</th>
    <?php endif; ?>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['work_date']) ?></td>
    <td><?= (int)$r['late_minutes'] ?></td>
    <?php if ($penaltiesEnabled): ?>
        <td><?= number_format((float)$r['amount'],2) ?> лей</td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>

<?php if (!$rows): ?>
<tr>
    <td colspan="<?= $penaltiesEnabled?3:2 ?>" style="opacity:.6">
        Нет опозданий за этот месяц 🎉
    </td>
</tr>
<?php endif; ?>
</table>

</main>
</div>

</body>
</html>