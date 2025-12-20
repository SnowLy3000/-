<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_admin();

$totalKnowledge = (int)$pdo->query("SELECT COUNT(*) FROM subthemes")->fetchColumn();

$branches = $pdo->query("
    SELECT id, title
    FROM branches
    ORDER BY title
")->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>📘 Статистика базы знаний</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.branch-card { margin-bottom:25px; }
.user-row {
    padding:6px 0;
    border-bottom:1px solid rgba(255,255,255,.1);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.bad { color:#ff6666; }
.good { color:#8bc34a; }
.small { opacity:.7;font-size:13px; }
button {
    background:#444;
    color:#fff;
    border:0;
    padding:4px 8px;
    border-radius:4px;
    cursor:pointer;
}
button:hover { background:#ff4444; }
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/knowledge_stats.php"><b>📘 База знаний</b></a>
    <a href="/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📊 Статистика базы знаний</h1>

<?php foreach ($branches as $b): ?>

<?php
$stmt = $pdo->prepare("
    SELECT id, username
    FROM users
    WHERE branch_id = ?
");
$stmt->execute([$b['id']]);
$users = $stmt->fetchAll();

$branchRead  = 0;
$branchTotal = count($users) * $totalKnowledge;
?>

<div class="card neon branch-card">
<h2><?= htmlspecialchars($b['title']) ?></h2>

<?php if (!$users): ?>
<p class="small">Нет сотрудников</p>
<?php continue; endif; ?>

<?php foreach ($users as $u): ?>
<?php
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM knowledge_views
    WHERE user_id = ?
");
$stmt->execute([$u['id']]);
$read = (int)$stmt->fetchColumn();

$percent = $totalKnowledge > 0 ? round(($read / $totalKnowledge) * 100) : 100;
$branchRead += $read;
?>
<div class="user-row <?= $percent < 100 ? 'bad' : 'good' ?>">
    <div>
        <?= htmlspecialchars($u['username']) ?>
        <span class="small">— <?= $read ?>/<?= $totalKnowledge ?> (<?= $percent ?>%)</span>
    </div>

    <?php if ($percent < 100): ?>
    <form method="post" action="/admin/send_knowledge_reminder.php">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <input type="hidden" name="subtheme_id" value="1">
        <button>Напомнить</button>
    </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php
$branchPercent = $branchTotal > 0
    ? round(($branchRead / $branchTotal) * 100)
    : 100;
?>
<div style="margin-top:10px;font-weight:bold;">
    Итог по филиалу: <?= $branchPercent ?>%
</div>

</div>
<?php endforeach; ?>

</main>
</div>
</body>
</html>