<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ только для тех, кто может смотреть KPI (уровень руководства)
require_role('manage_kpi_plans'); 

$monthInput = $_GET['month'] ?? date('Y-m');
$monthDate  = $monthInput . '-01';
$branchId   = (int)($_GET['branch_id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --- Загрузка филиалов --- */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* --- KPI данные (берем из зафиксированных фактов) --- */
$params = [$monthDate];
$where  = "DATE_FORMAT(kb.month_date, '%Y-%m-01') = ?";

if ($branchId) {
    $where .= " AND kb.branch_id = ?";
    $params[] = $branchId;
}

// Запрос из таблицы зафиксированных фактов kpi_bonuses/facts
$stmt = $pdo->prepare("
    SELECT
        CONCAT(u.last_name,' ',u.first_name) AS employee,
        kb.kpi_percent
    FROM kpi_bonuses kb
    JOIN users u ON u.id = kb.user_id
    WHERE $where
    ORDER BY kb.kpi_percent DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$labels = [];
$data   = [];
$colors = [];

foreach ($rows as $r) {
    $labels[] = $r['employee'];
    $val = (float)$r['kpi_percent'];
    $data[] = round($val, 1);

    // Палитра статус-кодов
    if ($val >= 100) {
        $colors[] = '#7CFF6B'; // Зеленый (План выполнен)
    } elseif ($val >= 80) {
        $colors[] = '#FFD166'; // Желтый (Близко)
    } else {
        $colors[] = '#FF6B6B'; // Красный (План провален)
    }
}
?>

<style>
    .chart-container { max-width: 1100px; margin: 0 auto; }
    
    /* Инфо-панель описания */
    .page-description { 
        display: flex; justify-content: space-between; align-items: flex-start; 
        margin-bottom: 30px; gap: 20px; flex-wrap: wrap;
    }
    .desc-text { flex: 1; min-width: 300px; }
    .desc-legend { 
        background: rgba(120, 90, 255, 0.05); border: 1px solid rgba(120, 90, 255, 0.15); 
        border-radius: 20px; padding: 20px; min-width: 300px; 
    }
    .legend-item { display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 8px; }
    .dot { width: 12px; height: 12px; border-radius: 3px; }

    .filter-card { background: rgba(255,255,255,0.02); border-radius: 24px; padding: 25px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 30px; }
    .st-input { 
        height: 46px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 12px; padding: 0 15px; color: #fff; outline: none; font-size: 14px;
    }
    .st-input:focus { border-color: #785aff; }
    
    .chart-box { 
        background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.01) 100%); 
        border-radius: 30px; border: 1px solid rgba(255,255,255,0.05); padding: 40px; 
        min-height: 400px; position: relative;
    }
</style>

<div class="chart-container">
    
    <div class="page-description">
        <div class="desc-text">
            <h1 style="margin:0; font-size: 32px; letter-spacing: -1px;">📊 Рейтинг эффективности</h1>
            <p class="muted" style="margin-top: 8px; font-size: 16px; line-height: 1.5;">
                Визуальный анализ выполнения планов. Сравнивайте результаты сотрудников и выявляйте лидеров продаж в реальном времени на основе закрытых периодов.
            </p>
        </div>
        
        <div class="desc-legend">
            <div style="font-size: 11px; font-weight: 800; color: #785aff; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1px;">Цветовая индикация:</div>
            <div class="legend-item">
                <div class="dot" style="background: #7CFF6B; box-shadow: 0 0 10px #7CFF6B66;"></div>
                <span><b>100%+</b> — План выполнен полностью</span>
            </div>
            <div class="legend-item">
                <div class="dot" style="background: #FFD166; box-shadow: 0 0 10px #FFD16666;"></div>
                <span><b>80-99%</b> — План близок к завершению</span>
            </div>
            <div class="legend-item">
                <div class="dot" style="background: #FF6B6B; box-shadow: 0 0 10px #FF6B6B66;"></div>
                <span><b>< 80%</b> — Низкий уровень выполнения</span>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <form method="get" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="kpi_chart">
            
            <div style="flex: 1; min-width: 150px;">
                <label class="muted" style="font-size: 10px; display:block; margin-bottom:8px; text-transform: uppercase; font-weight: 800;">Отчетный месяц</label>
                <input type="month" name="month" class="st-input" style="width: 100%;" value="<?= h($monthInput) ?>">
            </div>

            <div style="flex: 2; min-width: 200px;">
                <label class="muted" style="font-size: 10px; display:block; margin-bottom:8px; text-transform: uppercase; font-weight: 800;">Филиал / Группа</label>
                <select name="branch_id" class="st-input" style="width: 100%;">
                    <option value="0">Все филиалы (Общий рейтинг)</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId===$b['id']?'selected':'' ?>>
                            <?= h($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn" style="height: 46px; padding: 0 30px; border-radius: 12px; font-weight: 800;">ОБНОВИТЬ ГРАФИК</button>
        </form>
    </div>

    <div class="chart-box">
        <?php if (!$rows): ?>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                <div style="font-size: 50px; opacity: 0.1;">📉</div>
                <div class="muted" style="margin-top: 15px;">Нет данных для визуализации за этот месяц.<br>Возможно, KPI еще не были зафиксированы.</div>
            </div>
        <?php else: ?>
            <div style="position: relative; height: <?= count($rows) * 50 + 100 ?>px;">
                <canvas id="kpiChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($rows): ?>
<script>
Chart.defaults.color = 'rgba(255,255,255,0.4)';
Chart.defaults.font.family = "'Inter', sans-serif";

const ctx = document.getElementById('kpiChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Выполнение плана (%)',
            data: <?= json_encode($data) ?>,
            backgroundColor: <?= json_encode($colors) ?>,
            borderRadius: 8,
            borderSkipped: false,
            barThickness: 28,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1a1f35',
                titleColor: '#fff',
                bodyColor: '#7CFF6B',
                bodyFont: { size: 14, weight: '800' },
                padding: 15,
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Результат: ' + context.parsed.x + '%';
                    }
                }
            }
        },
        scales: {
            x: {
                min: 0,
                max: <?= max(110, count($data) ? max($data) + 10 : 110) ?>,
                grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                ticks: {
                    callback: value => value + '%',
                    stepSize: 20
                }
            },
            y: {
                grid: { display: false },
                ticks: {
                    color: '#fff',
                    font: { size: 13, weight: '600' }
                }
            }
        }
    }
});
</script>
<?php endif; ?>
