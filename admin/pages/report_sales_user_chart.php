<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

/* ===== ПРОВЕРКА ПРАВ И ОПРЕДЕЛЕНИЕ ID ===== */
$canViewOthers = can_user('view_kpi_user');
// Исправляем Warning: проверяем наличие в сессии или GET
$currentId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userId = (int)($_GET['user_id'] ?? $currentId);

if (!$canViewOthers && $userId !== $currentId) {
    echo "<div class='card' style='color:#ff4444;'>⛔ У вас нет прав для просмотра аналитики других сотрудников.</div>";
    return;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== ФИЛЬТРЫ ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

/* ===== СПРАВОЧНИКИ ===== */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

$users = [];
if ($canViewOthers) {
    $users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status='active' ORDER BY last_name")->fetchAll();
}

/* ===== ИМЯ СОТРУДНИКА ===== */
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch();
$userName = $userRow ? $userRow['first_name'].' '.$userRow['last_name'] : 'Неизвестный';

/* ===== ДАННЫЕ ДЛЯ ГРАФИКА ===== */
// ИСПРАВЛЕНО: считаем сумму через формулу, так как si.total_amount не существует
$sql = "
SELECT
    DATE(s.created_at) AS day,
    SUM(CEIL(si.price - (si.price * si.discount / 100)) * si.quantity) AS total_sum,
    COUNT(DISTINCT s.id) AS checks
FROM sales s
JOIN sale_items si ON si.sale_id = s.id
WHERE s.user_id = ?
  AND s.total_amount > 0
  AND DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$userId, $from, $to];
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
    .filter-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 20px; margin-bottom: 20px; }
    .st-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 8px 12px; color: #fff; outline: none; }
    .st-input:focus { border-color: #785aff; }
    .chart-container { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 25px; }
</style>

<div style="margin-bottom: 25px;">
    <h1 style="margin:0; font-size: 24px;">📈 Аналитика продаж</h1>
    <p class="muted" style="margin:5px 0 0 0;">Сотрудник: <b style="color:#b866ff;"><?= h($userName) ?></b></p>
</div>

<div class="filter-panel">
    <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items: center;">
        <input type="hidden" name="page" value="report_sales_user_chart">

        <?php if ($canViewOthers): ?>
            <div style="display:flex; flex-direction:column; gap:5px;">
                <span class="muted" style="font-size:10px; text-transform:uppercase;">Сотрудник</span>
                <select name="user_id" class="st-input">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id']==$userId?'selected':'' ?>><?= h($u['last_name'].' '.$u['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="user_id" value="<?= $userId ?>">
        <?php endif; ?>

        <div style="display:flex; flex-direction:column; gap:5px;">
            <span class="muted" style="font-size:10px; text-transform:uppercase;">Период</span>
            <div style="display:flex; gap:5px;">
                <input type="date" name="from" class="st-input" value="<?= $from ?>">
                <input type="date" name="to" class="st-input" value="<?= $to ?>">
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:5px;">
            <span class="muted" style="font-size:10px; text-transform:uppercase;">Локация</span>
            <select name="branch_id" class="st-input">
                <option value="0">Все филиалы</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn" style="height:40px; align-self:flex-end;">Показать</button>
    </form>
</div>

<div class="chart-container">
    <canvas id="userSalesChart" height="120"></canvas>
</div>

<script>
const ctx = document.getElementById('userSalesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            {
                label: 'Выручка (L)',
                data: <?= json_encode($sums) ?>,
                borderColor: '#785aff',
                backgroundColor: 'rgba(120, 90, 255, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Чеки',
                data: <?= json_encode($checks) ?>,
                borderColor: '#7CFF6B',
                borderDash: [5, 5],
                tension: 0.1,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { type: 'linear', position: 'left', ticks: { color: '#785aff' } },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#7CFF6B' } }
        }
    }
});
</script>
