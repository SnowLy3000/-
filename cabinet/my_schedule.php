<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$userId   = $_SESSION['user']['id'];
$fullName = trim($_SESSION['user']['fullname'] ?? 'Сотрудник');

/* ===== МЕСЯЦ ===== */
$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1 || $month > 12) $month = date('m');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$today = date('Y-m-d');

/* ===== СМЕНЫ СОТРУДНИКА ===== */
$stmt = $pdo->prepare("
    SELECT ws.work_date, b.title AS branch
    FROM work_schedule ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.user_id = ?
      AND YEAR(ws.work_date) = ?
      AND MONTH(ws.work_date) = ?
");
$stmt->execute([$userId, $year, $month]);

$workDays = [];
$branches = [];

foreach ($stmt->fetchAll() as $row) {
    $workDays[$row['work_date']] = $row['branch'];
    $branches[$row['branch']] = true;
}

$shiftCount = count($workDays);

/* ===== ДНИ НЕДЕЛИ ===== */
$weekDays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Мой график</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.calendar {
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:8px;
    margin-top:20px;
}
.day {
    padding:12px;
    border-radius:12px;
    min-height:80px;
    font-size:14px;
    transition:.2s;
}
.day.worked {
    background:#1f3b2c;
    border:1px solid #3fa86b;
}
.day.future-work {
    background:#1f2f3b;
    border:1px solid #4fa3ff;
}
.day.past {
    background:#222;
    color:#777;
}
.day.future {
    background:#1e1e2a;
}
.day-num {
    font-weight:bold;
    opacity:.8;
}
.branch {
    font-size:12px;
    margin-top:6px;
    opacity:.9;
}
.summary {
    margin-top:30px;
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <b><?= htmlspecialchars($fullName) ?></b>
    <a href="/cabinet/index.php">← Назад</a>
    <a href="/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📅 Мой график</h1>

<div style="display:flex;gap:12px;align-items:center">
    <a class="btn" href="?month=<?= $month-1 ?>&year=<?= $year ?>">←</a>
    <b><?= date('F Y', strtotime("$year-$month-01")) ?></b>
    <a class="btn" href="?month=<?= $month+1 ?>&year=<?= $year ?>">→</a>
</div>

<div class="calendar">

<?php foreach ($weekDays as $wd): ?>
    <div style="text-align:center;opacity:.6"><?= $wd ?></div>
<?php endforeach; ?>

<?php
$firstDay = (int)date('N', strtotime("$year-$month-01"));
for ($i=1; $i<$firstDay; $i++) echo '<div></div>';

for ($day=1; $day<=$daysInMonth; $day++):
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $worked = isset($workDays[$date]);
    $past = $date < $today;
?>
<div class="day
    <?= $worked && $past ? 'worked' : '' ?>
    <?= $worked && !$past ? 'future-work' : '' ?>
    <?= !$worked && $past ? 'past' : 'future' ?>
">
    <div class="day-num"><?= $day ?></div>
    <?php if ($worked): ?>
        <div class="branch"><?= htmlspecialchars($workDays[$date]) ?></div>
    <?php endif; ?>
</div>
<?php endfor; ?>

</div>

<div class="summary">
    <h3>📊 Итог за месяц</h3>
    <p>🧮 Смен: <b><?= $shiftCount ?></b></p>
    <p>🏬 Филиалы: <b><?= implode(', ', array_keys($branches)) ?: '—' ?></b></p>
</div>

</main>
</div>

</body>
</html>