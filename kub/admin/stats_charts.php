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

// Ответили на анкеты
$answeredEmployees = (int)$pdo->query("
    SELECT COUNT(DISTINCT user_id)
    FROM survey_answers
")->fetchColumn();

$notAnsweredEmployees = max(0, $totalEmployees - $answeredEmployees);

// Статусы сотрудников
$stats = [
    'ok' => 0,
    'partial' => 0,
    'bad' => 0
];

// Активные анкеты
$totalSurveys = (int)$pdo->query("
    SELECT COUNT(*)
    FROM surveys
    WHERE active = 1
")->fetchColumn();

// Ответы по сотрудникам
$answers = $pdo->query("
    SELECT user_id, COUNT(DISTINCT survey_id) AS cnt
    FROM survey_answers
    GROUP BY user_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Сотрудники
$users = $pdo->query("
    SELECT id
    FROM users
    WHERE role = 'employee' AND status = 'active'
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $uid) {
    $answered = (int)($answers[$uid] ?? 0);

    if ($totalSurveys === 0) {
        continue;
    } elseif ($answered === 0) {
        $stats['bad']++;
    } elseif ($answered < $totalSurveys) {
        $stats['partial']++;
    } else {
        $stats['ok']++;
    }
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Графики</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/stats.php">Общая статистика</a>
    <a href="/admin/stats_users.php">По сотрудникам</a>
    <a href="/admin/stats_charts.php"><b>📈 Графики</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📈 Визуальная статистика</h1>

<div class="card neon" style="display:flex;gap:40px;flex-wrap:wrap;">

    <div style="width:380px;">
        <h3>Анкеты</h3>
        <canvas id="surveyChart"></canvas>
    </div>

    <div style="width:380px;">
        <h3>Сотрудники</h3>
        <canvas id="userChart"></canvas>
    </div>

</div>

</main>
</div>

<script>
new Chart(document.getElementById('surveyChart'), {
    type: 'doughnut',
    data: {
        labels: ['Ответили', 'Не ответили'],
        datasets: [{
            data: [<?= $answeredEmployees ?>, <?= $notAnsweredEmployees ?>],
            backgroundColor: ['#3cffc3', '#ff5c5c']
        }]
    }
});

new Chart(document.getElementById('userChart'), {
    type: 'pie',
    data: {
        labels: ['ОК', 'Частично', 'Проблема'],
        datasets: [{
            data: [
                <?= $stats['ok'] ?>,
                <?= $stats['partial'] ?>,
                <?= $stats['bad'] ?>
            ],
            backgroundColor: ['#3cffc3', '#ffcc66', '#ff5c5c']
        }]
    }
});
</script>

</body>
</html>