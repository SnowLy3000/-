<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

// Все активные темы
$themes = $pdo->query("
    SELECT id, title
    FROM themes
    ORDER BY title
")->fetchAll();

// Кол-во активных сотрудников
$totalEmployees = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'employee' AND status = 'active'
")->fetchColumn();

$stats = [];

foreach ($themes as $t) {

    // Анкеты по теме
    $surveys = $pdo->prepare("
        SELECT id
        FROM surveys
        WHERE active = 1 AND theme_id = ?
    ");
    $surveys->execute([$t['id']]);
    $surveyIds = $surveys->fetchAll(PDO::FETCH_COLUMN);

    if (!$surveyIds) {
        continue;
    }

    // Кто ответил хотя бы на одну анкету по теме
    $placeholders = implode(',', array_fill(0, count($surveyIds), '?'));

    $answered = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id)
        FROM survey_answers
        WHERE survey_id IN ($placeholders)
    ");
    $answered->execute($surveyIds);
    $answeredCount = (int)$answered->fetchColumn();

    $stats[] = [
        'theme' => $t['title'],
        'answered' => $answeredCount,
        'total' => $totalEmployees
    ];
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Статистика по темам</title>

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
.good { color:#9ff; }
.warn { color:#ffcc66; }
.bad  { color:#ff5555; }
</style>

</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/stats.php">Общая статистика</a>
    <a href="/admin/stats_users.php">По сотрудникам</a>
    <a href="/admin/stats_themes.php"><b>По темам</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📊 Статистика по темам</h1>

<?php if (!$stats): ?>
    <p>Анкет по темам пока нет.</p>
<?php else: ?>

<table>
<thead>
<tr>
    <th>Тема</th>
    <th>Ответили</th>
    <th>Статус</th>
</tr>
</thead>
<tbody>

<?php foreach ($stats as $s): ?>
    <?php
        if ($s['answered'] === 0) {
            $status = '🔴 Критично';
            $class = 'bad';
        } elseif ($s['answered'] < $s['total']) {
            $status = '🟡 Частично';
            $class = 'warn';
        } else {
            $status = '🟢 ОК';
            $class = 'good';
        }
    ?>
    <tr>
        <td><?= htmlspecialchars($s['theme']) ?></td>
        <td><?= $s['answered'] ?> / <?= $s['total'] ?></td>
        <td class="<?= $class ?>"><?= $status ?></td>
    </tr>
<?php endforeach; ?>

</tbody>
</table>

<?php endif; ?>

</main>
</div>

</body>
</html>