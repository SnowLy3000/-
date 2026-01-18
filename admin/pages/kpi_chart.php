<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== ФИЛЬТРЫ ===== */
$year = $_GET['year'] ?? date('Y');
$branchId = (int)($_GET['branch_id'] ?? 0);
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* ===== ПОДГОТОВКА ДАННЫХ ДЛЯ ГРАФИКА (ПО МЕСЯЦАМ) ===== */
$chartData = [];
for ($m = 1; $m <= 12; $m++) {
    $monthStr = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $chartData[$monthStr] = [
        'total' => 0, 
        'promo' => 0, 
        'clients' => 0,
        'label' => date('M', mktime(0, 0, 0, $m, 1))
    ];
}

/* ===== ЗАПРОС ДАННЫХ ===== */
$sql = "
    SELECT 
        DATE_FORMAT(s.created_at, '%Y-%m') as m_key,
        SUM(s.total_amount) as m_total,
        -- Считаем количество акционных чеков за месяц
        SUM(CASE WHEN (
            SELECT COUNT(*) FROM sale_items si 
            JOIN product_promotions pr ON pr.product_name = si.product_name 
            WHERE si.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
        ) > 0 THEN 1 ELSE 0 END) as m_promo,
        -- Считаем чеки с клиентами
        SUM(CASE WHEN s.client_id IS NOT NULL THEN 1 ELSE 0 END) as m_clients
    FROM sales s
    WHERE DATE_FORMAT(s.created_at, '%Y') = :year
";

if ($branchId) $sql .= " AND s.branch_id = :bid";
$sql .= " GROUP BY m_key";

$stmt = $pdo->prepare($sql);
$params = [':year' => $year];
if ($branchId) $params[':bid'] = $branchId;
$stmt->execute($params);

while ($row = $stmt->fetch()) {
    if (isset($chartData[$row['m_key']])) {
        $chartData[$row['m_key']]['total'] = (float)$row['m_total'];
        $chartData[$row['m_key']]['promo'] = (int)$row['m_promo'];
        $chartData[$row['m_key']]['clients'] = (int)$row['m_clients'];
    }
}

// Формируем массивы для JS
$labels = []; $totals = []; $promos = []; $clients = [];
foreach ($chartData as $data) {
    $labels[] = $data['label'];
    $totals[] = $data['total'];
    $promos[] = $data['promo'];
    $clients[] = $data['clients'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .chart-container { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    
    /* Компактный фильтр */
    .chart-filter { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 20px; padding: 15px 20px; margin-bottom: 25px;
        display: flex; gap: 15px; align-items: flex-end;
    }
    .f-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
    .f-group label { font-size: 9px; font-weight: 800; color: rgba(255,255,255,0.3); text-transform: uppercase; }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    .btn-update { height: 38px; background: #785aff; color: #fff; border: none; border-radius: 10px; padding: 0 25px; font-weight: 700; cursor: pointer; }

    /* Боксы для графиков */
    .chart-box { 
        background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 24px; padding: 25px; margin-bottom: 25px;
    }
    .chart-title { font-size: 16px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .chart-title span { font-size: 10px; background: rgba(120,90,255,0.1); color: #785aff; padding: 3px 8px; border-radius: 6px; }
</style>

<div class="chart-container">
    <div style="margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 24px; font-weight: 900;">📊 Динамика показателей</h1>
        <p style="margin:0; font-size: 13px; opacity: 0.4;">Визуализация роста выручки и эффективности акций</p>
    </div>

    <form class="chart-filter">
        <input type="hidden" name="page" value="kpi_chart">
        <div class="f-group">
            <label>Выберите филиал</label>
            <select name="branch_id" class="st-input" style="width: 100%;">
                <option value="0">Все локации</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="f-group" style="flex: 0.5;">
            <label>Год</label>
            <input type="number" name="year" class="st-input" value="<?= h($year) ?>" min="2020" max="2030">
        </div>
        <button class="btn-update">Обновить графики</button>
    </form>

    <div class="chart-box">
        <div class="chart-title">💰 Тренд выручки <span>MDL / Month</span></div>
        <canvas id="salesChart" height="100"></canvas>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
        <div class="chart-box">
            <div class="chart-title" style="color: #ff4b2b;">🔥 Активность акций <span>Чеков</span></div>
            <canvas id="promoChart" height="150"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-title" style="color: #7CFF6B;">👤 Прирост лояльности <span>Клиенты</span></div>
            <canvas id="clientChart" height="150"></canvas>
        </div>
    </div>
</div>

<script>
const ctxSales = document.getElementById('salesChart').getContext('2d');
new Chart(ctxSales, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Выручка (MDL)',
            data: <?= json_encode($totals) ?>,
            borderColor: '#785aff',
            backgroundColor: 'rgba(120, 90, 255, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#785aff'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.3)' } },
            x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.3)' } }
        }
    }
});

const ctxPromo = document.getElementById('promoChart').getContext('2d');
new Chart(ctxPromo, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Акционные чеки',
            data: <?= json_encode($promos) ?>,
            backgroundColor: '#ff4b2b',
            borderRadius: 6
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { display: false } },
            x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 10 } } }
        }
    }
});

const ctxClient = document.getElementById('clientChart').getContext('2d');
new Chart(ctxClient, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Чеки с клиентами',
            data: <?= json_encode($clients) ?>,
            backgroundColor: '#7CFF6B',
            borderRadius: 6
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { display: false } },
            x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 10 } } }
        }
    }
});
</script>