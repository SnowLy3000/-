<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_kpi_general');

// Функция безопасности (защита от XSS), если она не определена глобально
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

/* === ФИЛИАЛЫ === */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* === ДАННЫЕ ДЛЯ ГРАФИКА === */
$sql = "
SELECT 
    DATE(s.created_at) as day,
    SUM(s.total_amount) AS total_sum,
    COUNT(DISTINCT s.id) AS checks
FROM sales s
WHERE s.total_amount > 0 
  AND DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$from, $to];

// Исправленный фильтр локации
if ($branchId > 0) {
    $sql .= " AND s.branch_id = ?";
    $params[] = $branchId;
}

$sql .= " GROUP BY day ORDER BY day";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$labels = []; $sums = []; $checks = [];
foreach ($data as $r) {
    $labels[] = date('d.m', strtotime($r['day']));
    $sums[]   = (float)$r['total_sum'];
    $checks[] = (int)$r['checks'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .analytics-shell { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    .filter-row { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); 
        padding: 15px 20px; border-radius: 20px; display: flex; gap: 12px; align-items: flex-end; margin-bottom: 25px;
    }
    .f-item { display: flex; flex-direction: column; gap: 5px; flex: 1; }
    .f-item label { font-size: 9px; font-weight: 800; color: rgba(255,255,255,0.3); text-transform: uppercase; }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    .chart-box { 
        background: #0f0f13; border: 1px solid #1f1f23; border-radius: 28px; 
        padding: 30px; position: relative; min-height: 450px;
    }
    .btn-update { background: #785aff; color: #fff; border: none; height: 38px; padding: 0 20px; border-radius: 10px; font-weight: 700; cursor: pointer; }
</style>

<div class="analytics-shell">
    <div style="margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 24px; font-weight: 900;">📈 Аналитика динамики</h1>
        <p style="margin:5px 0 0 0; font-size: 13px; opacity: 0.4;">Сравнение выручки и покупательской активности</p>
    </div>

    <form class="filter-row" method="GET">
        <input type="hidden" name="page" value="report_sales_chart">
        <div class="f-item">
            <label>Начало</label>
            <input type="date" name="from" class="st-input" value="<?= h($from) ?>">
        </div>
        <div class="f-item">
            <label>Конец</label>
            <input type="date" name="to" class="st-input" value="<?= h($to) ?>">
        </div>
        <div class="f-item" style="flex: 2;">
            <label>Локация</label>
            <select name="branch_id" class="st-input" style="width: 100%;">
                <option value="0">Все филиалы</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-update">Применить</button>
    </form>

    <div class="chart-box">
        <canvas id="mainSalesChart"></canvas>
    </div>
</div>

<script>
const ctx = document.getElementById('mainSalesChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(120, 90, 255, 0.3)');
gradient.addColorStop(1, 'rgba(120, 90, 255, 0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            {
                label: 'Выручка (L)',
                data: <?= json_encode($sums) ?>,
                borderColor: '#785aff',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                borderWidth: 4,
                yAxisID: 'y',
                pointRadius: 0,
                pointHoverRadius: 6
            },
            {
                label: 'Чеки (шт)',
                data: <?= json_encode($checks) ?>,
                borderColor: 'rgba(124, 255, 107, 0.5)',
                borderDash: [5, 5],
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                yAxisID: 'y1',
                pointRadius: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                align: 'end',
                labels: { color: '#82828e', font: { size: 10, weight: '700' }, usePointStyle: true, padding: 20 }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#41414c' } },
            y: { position: 'left', grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#785aff' } },
            y1: { position: 'right', grid: { display: false }, ticks: { color: '#7CFF6B' } }
        }
    }
});
</script>