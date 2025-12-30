<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ по праву общей KPI аналитики
require_role('view_kpi_general');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

/* === ФИЛИАЛЫ === */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* === ДАННЫЕ ДЛЯ ГРАФИКА === */
// ИСПРАВЛЕНО: Мы считаем сумму из sale_items по формуле, так как колонки total_amount в si нет
$sql = "
SELECT 
    DATE(s.created_at) as day,
    SUM(CEIL(si.price - (si.price * si.discount / 100)) * si.quantity) AS total_sum,
    COUNT(DISTINCT s.id) AS checks
FROM sales s
JOIN sale_items si ON si.sale_id = s.id
WHERE s.total_amount > 0 
  AND DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$from, $to];

if ($branchId) {
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
    .report-filters { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-top: 15px; }
    .st-input { 
        height: 42px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 12px; padding: 0 12px; color: #fff; outline: none; font-size: 14px; transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }
    .chart-container { 
        padding: 30px; background: rgba(255,255,255,0.02); border-radius: 24px; 
        border: 1px solid rgba(255,255,255,0.05); margin-top: 20px;
    }
    .chart-title-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .btn-update { background: #785aff; color: #fff; border: none; height: 42px; padding: 0 30px; border-radius: 12px; cursor: pointer; font-weight: bold; transition: 0.3s; }
    .btn-update:hover { background: #6344d4; transform: translateY(-2px); }
</style>

<div class="chart-title-box">
    <div>
        <h2 style="margin:0">📈 Аналитика сети</h2>
        <p class="muted" style="margin:5px 0 0 0;">Динамика выручки и количество транзакций</p>
    </div>
</div>

<div class="card" style="border-radius: 20px; margin-bottom: 20px;">
    <form method="get" class="report-filters">
        <input type="hidden" name="page" value="report_sales_chart">
        
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Начало</label>
            <input type="date" name="from" class="st-input" value="<?= $from ?>">
        </div>

        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Конец</label>
            <input type="date" name="to" class="st-input" value="<?= $to ?>">
        </div>

        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Филиал</label>
            <select name="branch_id" class="st-input">
                <option value="0">Все локации</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-update">Обновить данные</button>
    </form>
</div>

<div class="chart-container">
    <canvas id="salesChart" height="110"></canvas>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');

const fillGradient = ctx.createLinearGradient(0, 0, 0, 400);
fillGradient.addColorStop(0, 'rgba(120, 90, 255, 0.2)');
fillGradient.addColorStop(1, 'rgba(120, 90, 255, 0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            {
                label: 'Выручка (L)',
                data: <?= json_encode($sums) ?>,
                borderColor: '#785aff',
                backgroundColor: fillGradient,
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                yAxisID: 'y',
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#785aff'
            },
            {
                label: 'Чеки (шт)',
                data: <?= json_encode($checks) ?>,
                borderColor: '#4ade80',
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderDash: [5, 5],
                yAxisID: 'y1',
                pointRadius: 3
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: { color: '#fff', font: { family: 'Inter', size: 12 }, padding: 20 }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                ticks: { color: 'rgba(255,255,255,0.5)' }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                ticks: { color: '#785aff' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { color: '#4ade80' }
            }
        }
    }
});
</script>
