<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();

$stmt = $pdo->query("
    SELECT 
        u.fullname,
        b.title AS branch,
        wc.checkin_time,
        wc.late_minutes
    FROM work_checkins wc
    JOIN users u ON u.id = wc.user_id
    JOIN branches b ON b.id = wc.branch_id
    WHERE wc.work_date = CURDATE()
      AND wc.late_minutes > 0
    ORDER BY wc.late_minutes DESC
");
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Опоздания</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">
<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
</aside>

<main class="admin-main">
<h1>⏰ Опоздания сегодня</h1>

<?php if (!$rows): ?>
    <p>Нет опозданий 🎉</p>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<div class="card neon">
    <b><?= htmlspecialchars($r['fullname']) ?></b><br>
    🏬 <?= htmlspecialchars($r['branch']) ?><br>
    ⏱ <?= date('H:i', strtotime($r['checkin_time'])) ?><br>
    ❗ Опоздание: <?= (int)$r['late_minutes'] ?> мин
</div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>