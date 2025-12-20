<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

/* ===== ДАННЫЕ ===== */

// Активные анкеты
$totalSurveys = (int)$pdo->query("
    SELECT COUNT(*)
    FROM surveys
    WHERE active = 1
")->fetchColumn();

// Сотрудники
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'employee' AND status = 'active'
    ORDER BY fullname
")->fetchAll();

// Ответы сотрудников
$answers = $pdo->query("
    SELECT user_id, COUNT(DISTINCT survey_id) AS cnt
    FROM survey_answers
    GROUP BY user_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Статистика по сотрудникам</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
table {
    width:100%;
    border-collapse:collapse;
}
th, td {
    padding:10px;
    border-bottom:1px solid #333;
}
.ok   { color:#9ff; font-weight:bold; }
.warn { color:#ffcc66; font-weight:bold; }
.bad  { color:#ff5555; font-weight:bold; }
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/stats.php">📊 Общая статистика</a>
    <a href="/admin/stats_users.php"><b>👥 По сотрудникам</b></a>
    <a href="/admin/stats_charts.php">📈 Графики</a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📊 Статистика по сотрудникам</h1>

<table>
<thead>
<tr>
    <th>Сотрудник</th>
    <th>Анкеты</th>
    <th>Статус</th>
</tr>
</thead>
<tbody>

<?php foreach ($users as $u): ?>
<?php
    $answered = (int)($answers[$u['id']] ?? 0);

    if ($totalSurveys === 0) {
        $status = '—';
        $class  = '';
    } elseif ($answered === 0) {
        $status = '🔴 Проблема';
        $class  = 'bad';
    } elseif ($answered < $totalSurveys) {
        $status = '🟡 Частично';
        $class  = 'warn';
    } else {
        $status = '🟢 ОК';
        $class  = 'ok';
    }
?>
<tr>
    <td><?= htmlspecialchars($u['fullname']) ?></td>
    <td><?= $answered ?> / <?= $totalSurveys ?></td>
    <td class="<?= $class ?>"><?= $status ?></td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</main>
</div>

</body>
</html>