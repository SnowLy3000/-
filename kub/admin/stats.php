<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

/* ===== ДАННЫЕ ===== */

// Сотрудники
$totalEmployees = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'employee' AND status = 'active'
")->fetchColumn();

// Активные анкеты
$totalSurveys = (int)$pdo->query("
    SELECT COUNT(*)
    FROM surveys
    WHERE active = 1
")->fetchColumn();

// Ответили хотя бы на одну анкету
$answeredEmployees = (int)$pdo->query("
    SELECT COUNT(DISTINCT user_id)
    FROM survey_answers
")->fetchColumn();

// Не ответили ни на одну
$notAnsweredEmployees = max(0, $totalEmployees - $answeredEmployees);

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Статистика</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.stat {
    font-size: 22px;
    margin-bottom: 12px;
}
.good { color:#9ff; }
.bad  { color:#ff5555; }
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/stats.php"><b>📊 Общая статистика</b></a>
    <a href="/admin/stats_users.php">👥 По сотрудникам</a>
    <a href="/admin/stats_charts.php">📈 Графики</a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📊 Общая статистика обучения</h1>

<div class="card neon">
    <div class="stat">👥 Сотрудников: <b><?= $totalEmployees ?></b></div>
    <div class="stat">📋 Активных анкет: <b><?= $totalSurveys ?></b></div>
    <div class="stat good">✅ Ответили хотя бы на одну анкету: <b><?= $answeredEmployees ?></b></div>
    <div class="stat bad">❌ Не ответили ни на одну анкету: <b><?= $notAnsweredEmployees ?></b></div>
</div>

<p style="margin-top:20px;opacity:.7;">
    Статистика по тестам будет добавлена после внедрения результатов тестирования.
</p>

</main>
</div>

</body>
</html>