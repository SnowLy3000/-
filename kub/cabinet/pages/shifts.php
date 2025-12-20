<?php
/**
 * Страница: График смен сотрудника
 */

require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/auth.php';

require_login(); // если у тебя есть такой хелпер, иначе проверка ниже

// защита на всякий
if (!isset($_SESSION['user'])) {
    header('Location: /public/index.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// текущий месяц
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

/* =========================
   СМЕНЫ СОТРУДНИКА
========================= */
$stmt = $pdo->prepare("
    SELECT 
        ws.work_date,
        ws.shift_type,
        b.title AS branch_title
    FROM work_schedule ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.user_id = ?
      AND ws.work_date BETWEEN ? AND ?
    ORDER BY ws.work_date
");
$stmt->execute([$userId, $monthStart, $monthEnd]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// группируем по дате
$shifts = [];
foreach ($rows as $r) {
    $shifts[$r['work_date']][] = $r;
}

// календарные данные
$daysInMonth = (int)date('t', strtotime($monthStart));
$firstWeekday = (int)date('N', strtotime($monthStart)); // 1..7
?>

<h1>📅 График смен</h1>

<p style="opacity:.7">
    Ваши смены за <b><?= sprintf('%02d.%04d', $month, $year) ?></b>
</p>

<!-- НАВИГАЦИЯ ПО МЕСЯЦАМ -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a class="btn"
       href="?page=shifts&year=<?= $month === 1 ? $year-1 : $year ?>&month=<?= $month === 1 ? 12 : $month-1 ?>">
        ◀ Предыдущий
    </a>

    <a class="btn"
       href="?page=shifts&year=<?= $month === 12 ? $year+1 : $year ?>&month=<?= $month === 12 ? 1 : $month+1 ?>">
        Следующий ▶
    </a>
</div>

<!-- КАЛЕНДАРЬ -->
<div class="card">
    <h3>Календарь</h3>

    <div style="
        display:grid;
        grid-template-columns: repeat(7, 1fr);
        gap:10px;
        margin-top:14px;
    ">
        <?php
        $weekdays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
        foreach ($weekdays as $w) {
            echo '<div style="opacity:.6;font-weight:700;text-align:center">'.$w.'</div>';
        }

        // пустые ячейки до первого дня
        for ($i=1; $i<$firstWeekday; $i++) {
            echo '<div></div>';
        }

        // дни месяца
        for ($d=1; $d<=$daysInMonth; $d++):
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $hasShift = isset($shifts[$date]);
        ?>
            <div style="
                padding:10px;
                border-radius:12px;
                background: <?= $hasShift ? 'rgba(46,204,113,.18)' : 'rgba(255,255,255,.04)' ?>;
                border:1px solid rgba(255,255,255,.12);
            ">
                <b><?= $d ?></b>

                <?php if ($hasShift): ?>
                    <?php foreach ($shifts[$date] as $s): ?>
                        <div style="font-size:13px;margin-top:6px;">
                            🏬 <?= htmlspecialchars($s['branch_title']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="opacity:.4;font-size:13px;margin-top:6px;">
                        Выходной
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- СПИСОК СМЕН -->
<div class="card" style="margin-top:20px;">
    <h3>Список смен</h3>

    <?php if (!$rows): ?>
        <div style="opacity:.6">В этом месяце смен нет</div>
    <?php else: ?>
        <?php foreach ($rows as $r): ?>
            <div style="
                padding:10px 0;
                border-bottom:1px solid rgba(255,255,255,.08);
            ">
                <b><?= date('d.m.Y', strtotime($r['work_date'])) ?></b>
                — <?= htmlspecialchars($r['branch_title']) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>