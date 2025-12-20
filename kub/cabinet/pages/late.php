<?php
/**
 * Страница: Опоздания сотрудника
 */

require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/auth.php';

require_login();

if (!isset($_SESSION['user'])) {
    header('Location: /public/index.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// месяц
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

/* =========================
   ОПОЗДАНИЯ
========================= */
$stmt = $pdo->prepare("
    SELECT 
        wc.work_date,
        wc.late_minutes,
        b.title AS branch_title
    FROM work_checkins wc
    JOIN branches b ON b.id = wc.branch_id
    WHERE wc.user_id = ?
      AND wc.late_minutes > 0
      AND wc.work_date BETWEEN ? AND ?
    ORDER BY wc.work_date DESC
");
$stmt->execute([$userId, $monthStart, $monthEnd]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// итоги
$totalDays = count($rows);
$totalMinutes = array_sum(array_column($rows, 'late_minutes'));
?>

<h1>⏰ Опоздания</h1>

<p style="opacity:.7">
    Статистика за <b><?= sprintf('%02d.%04d', $month, $year) ?></b>
</p>

<!-- НАВИГАЦИЯ -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a class="btn"
       href="?page=late&year=<?= $month === 1 ? $year-1 : $year ?>&month=<?= $month === 1 ? 12 : $month-1 ?>">
        ◀ Предыдущий
    </a>

    <a class="btn"
       href="?page=late&year=<?= $month === 12 ? $year+1 : $year ?>&month=<?= $month === 12 ? 1 : $month+1 ?>">
        Следующий ▶
    </a>
</div>

<!-- ИТОГИ -->
<div class="profile-grid">
    <div class="card">
        <h3>📅 Дней с опозданием</h3>
        <div style="font-size:28px;font-weight:900;">
            <?= $totalDays ?>
        </div>
    </div>

    <div class="card">
        <h3>🕒 Всего минут</h3>
        <div style="font-size:28px;font-weight:900;">
            <?= $totalMinutes ?>
        </div>
    </div>
</div>

<!-- СПИСОК -->
<div class="card" style="margin-top:20px;">
    <h3>Подробно</h3>

    <?php if (!$rows): ?>
        <div style="opacity:.6">
            🎉 В этом месяце нет опозданий
        </div>
    <?php else: ?>
        <?php foreach ($rows as $r): ?>
            <?php
                $mins = (int)$r['late_minutes'];
                if ($mins <= 5) $badge = 'green';
                elseif ($mins <= 15) $badge = 'orange';
                else $badge = 'red';
            ?>
            <div style="
                padding:12px 0;
                border-bottom:1px solid rgba(255,255,255,.08);
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:10px;
            ">
                <div>
                    <b><?= date('d.m.Y', strtotime($r['work_date'])) ?></b><br>
                    <span style="opacity:.7">
                        🏬 <?= htmlspecialchars($r['branch_title']) ?>
                    </span>
                </div>

                <span class="badge <?= $badge ?>">
                    <?= $mins ?> мин
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>