<?php
require_once __DIR__ . '/../../includes/db.php';

$user = current_user();

// Обработка выбранного месяца
$ym = $_GET['ym'] ?? date('Y-m');
$time = strtotime($ym . '-01');
if (!$time) {
    $ym = date('Y-m');
    $time = strtotime($ym . '-01');
}

$firstDay = date('Y-m-01', $time);
$lastDay  = date('Y-m-t', $time);

$prevYm = date('Y-m', strtotime('-1 month', $time));
$nextYm = date('Y-m', strtotime('+1 month', $time));

// Получение смен сотрудника из БД
$stmt = $pdo->prepare("
    SELECT ws.*, b.name AS branch_name
    FROM work_shifts ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.user_id = ?
      AND ws.shift_date BETWEEN ? AND ?
    ORDER BY ws.shift_date, ws.start_time
");
$stmt->execute([$user['id'], $firstDay, $lastDay]);
$rows = $stmt->fetchAll();

// Группировка смен по датам
$byDate = [];
foreach ($rows as $r) {
    $byDate[$r['shift_date']][] = $r;
}

$daysInMonth = date('t', $time);
$startWeekDay = (int)date('N', strtotime($firstDay));
?>

<style>
    /* Адаптация под темный фон вашего сайта */
    .schedule-compact {
        font-family: 'Inter', -apple-system, sans-serif;
        background: transparent; /* Фон берется от родительской card */
        color: #fff;
        max-width: 800px; /* Уменьшили общую ширину */
        margin: 0 auto;
    }

    .calendar-header-mini {
        text-align: center;
        margin-bottom: 20px;
    }

    .calendar-header-mini h2 {
        font-size: 22px; /* Меньше шрифт */
        font-weight: 300;
        text-transform: uppercase;
        letter-spacing: 4px;
        margin: 0;
        color: #fff;
    }

    /* Навигация компактная */
    .calendar-nav-mini {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .calendar-nav-mini .nav-btn {
        text-decoration: none;
        color: rgba(255,255,255,0.4);
        font-size: 16px;
        padding: 5px 10px;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        transition: 0.3s;
    }

    .calendar-nav-mini .nav-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
    }

    .calendar-nav-mini .month-label {
        font-size: 14px;
        font-weight: 600;
        min-width: 100px;
        text-align: center;
    }

    /* Таблица */
    .compact-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .compact-table th {
        font-size: 10px;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        padding-bottom: 10px;
    }

    .compact-table td {
        height: 70px; /* Значительно уменьшили высоту */
        vertical-align: top;
        padding: 4px;
        border: 1px solid rgba(255,255,255,0.08); /* Границы как в вашем коде */
    }

    .day-cell {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .day-num-mini {
        font-size: 13px;
        color: rgba(255,255,255,0.6);
        margin-bottom: 4px;
    }

    /* Текущий день в стиле вашего сайта (фиолетовый акцент) */
    .is-today-mini {
        background: rgba(120,90,255,0.15) !important;
    }
    .is-today-mini .day-num-mini {
        color: #785aff;
        font-weight: 700;
    }

    /* Плашка филиала компактная */
    .branch-mini-tag {
        background: rgba(255,255,255,0.08);
        border-left: 2px solid #785aff;
        padding: 3px 5px;
        border-radius: 4px;
        font-size: 9px; /* Маленький текст */
        color: #ddd;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 2px;
    }

    .empty-day-mini {
        background: rgba(0,0,0,0.1);
        opacity: 0.3;
    }
</style>

<div class="card">
    <div class="schedule-compact">
        <div class="calendar-header-mini">
            <h2><?= date('F', $time) ?></h2>
        </div>

        <div class="calendar-nav-mini">
            <a class="nav-btn" href="/cabinet/index.php?page=schedule&ym=<?= $prevYm ?>">◀</a>
            <span class="month-label"><?= date('M Y', $time) ?></span>
            <a class="nav-btn" href="/cabinet/index.php?page=schedule&ym=<?= $nextYm ?>">▶</a>
        </div>

        <table class="compact-table">
            <thead>
                <tr>
                    <th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $cell = 1;
                for ($i = 1; $i < $startWeekDay; $i++) {
                    echo '<td class="empty-day-mini"></td>';
                    $cell++;
                }

                for ($day = 1; $day <= $daysInMonth; $day++, $cell++) {
                    $date = sprintf('%s-%02d', $ym, $day);
                    $isToday = ($date === date('Y-m-d'));
                    $tdClass = $isToday ? 'is-today-mini' : '';

                    echo '<td class="'.$tdClass.'">';
                    echo '<div class="day-cell">';
                    echo '<span class="day-num-mini">'.$day.'</span>';

                    if (!empty($byDate[$date])) {
                        foreach ($byDate[$date] as $s) {
                            echo '<div class="branch-mini-tag" title="'.htmlspecialchars($s['branch_name']).'">';
                            echo htmlspecialchars($s['branch_name']);
                            echo '</div>';
                        }
                    }

                    echo '</div>';
                    echo '</td>';

                    if ($cell % 7 === 0 && $day < $daysInMonth) echo '</tr><tr>';
                }

                while (($cell - 1) % 7 !== 0) {
                    echo '<td class="empty-day-mini"></td>';
                    $cell++;
                }
                ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>
