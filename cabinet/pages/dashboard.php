<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();
$user = current_user();
$userId = (int)$user['id'];

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');

/* === ОБРАБОТКА ОБНОВЛЕНИЯ ЛИЧНОГО ПЛАНА === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
    $newPlan = (float)$_POST['new_plan'];
    if ($newPlan > 0) {
        $stmt = $pdo->prepare("UPDATE users SET daily_plan_kpi = ? WHERE id = ?");
        $stmt->execute([$newPlan, $userId]);
        exit(json_encode(['success' => true]));
    }
}

/* === ФУНКЦИЯ СБОРА ДАННЫХ === */
function getFullStats($pdo, $userId, $from, $to) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS checks, 
            COALESCE(SUM(total_amount), 0) AS sum_total,
            (SELECT COALESCE(SUM(si.salary_amount), 0) 
             FROM sale_items si 
             JOIN sales s2 ON s2.id = si.sale_id 
             WHERE s2.user_id = ? AND s2.created_at BETWEEN ? AND ?) as total_salary,
            COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales,
            COUNT(DISTINCT DATE(s.created_at)) as active_days
        FROM sales s 
        WHERE s.user_id = ? AND s.created_at BETWEEN ? AND ? AND s.total_amount > 0
    ");
    $stmt->execute([$userId, $from, $to, $userId, $from, $to]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$todayStats = getFullStats($pdo, $userId, $today.' 00:00:00', $today.' 23:59:59');
$monthStats = getFullStats($pdo, $userId, $monthStart.' 00:00:00', date('Y-m-d 23:59:59'));

/* === ЛОГИКА СМЕН === */
$stmt = $pdo->prepare("SELECT ws.branch_id, b.name AS branch_name FROM work_shifts ws JOIN branches b ON b.id = ws.branch_id WHERE ws.user_id = ? AND ws.shift_date = ? LIMIT 1");
$stmt->execute([$userId, $today]);
$todayShift = $stmt->fetch();

$activeSession = null;
if ($todayShift) {
    $stmt = $pdo->prepare("SELECT * FROM shift_sessions WHERE user_id = ? AND branch_id = ? AND checkout_at IS NULL ORDER BY checkin_at DESC LIMIT 1");
    $stmt->execute([$userId, $todayShift['branch_id']]);
    $activeSession = $stmt->fetch();
}

/* === KPI ПЛАН === */
$stmt = $pdo->prepare("SELECT daily_plan_kpi FROM users WHERE id = ?");
$stmt->execute([$userId]);
$dailyPlan = (float)($stmt->fetchColumn() ?: 5000);
$todayPercent = $dailyPlan > 0 ? min(100, ($todayStats['sum_total'] / $dailyPlan) * 100) : 0;
?>

<style>
    .dashboard { font-family: 'Inter', sans-serif; max-width: 900px; margin: 0 auto; color: #fff; }
    .welcome-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 0 10px; }
    .status-card { background: linear-gradient(135deg, rgba(120,90,255,0.2) 0%, rgba(120,90,255,0.05) 100%); border: 1px solid rgba(120,90,255,0.3); border-radius: 24px; padding: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 25px; }
    .kpi-container { background: rgba(255,255,255,0.03); border-radius: 24px; padding: 25px; border: 1px solid rgba(255,255,255,0.05); }
    .kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kpi-box { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.03); }
    .kpi-box h4 { margin: 0 0 15px 0; font-size: 11px; text-transform: uppercase; color: #785aff; letter-spacing: 1.5px; font-weight: 700; border-bottom: 1px solid rgba(120,90,255,0.2); padding-bottom: 8px;}
    .salary-tag { font-size: 22px; font-weight: 800; color: #7CFF6B; display: block; margin-bottom: 10px; }
    .progress-bg { height: 8px; background: rgba(255,255,255,0.05); border-radius: 10px; margin: 15px 0 5px 0; overflow: hidden; }
    .progress-fill { height: 100%; background: #785aff; border-radius: 10px; transition: 1s ease-out; }
    .m-line { display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
    .m-line span { color: rgba(255,255,255,0.5); }
    .m-line b { color: #fff; }
    @media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }
</style>

<div class="dashboard">
    <div class="welcome-bar">
        <div>
            <h1 style="margin:0;">Привет, <?= h($user['first_name']) ?>! 👋</h1>
            <span style="opacity:0.5; font-size:14px;"><?= date('d F, Y') ?></span>
        </div>
        <div style="text-align:right;">
            <div style="background:rgba(255,255,255,0.05); padding:8px 15px; border-radius:15px; font-size:13px; font-weight:600;">
                📍 <?= h($todayShift['branch_name'] ?? 'Выходной') ?>
            </div>
        </div>
    </div>

    <div class="status-card">
        <div style="font-size: 40px;"><?= $activeSession ? '🟢' : ($todayShift ? '🟡' : '🏠') ?></div>
        <div>
            <b style="font-size: 18px; display: block;"><?= $activeSession ? 'Вы в смене' : ($todayShift ? 'Ожидание Check-in' : 'У вас сегодня выходной') ?></b>
            <span style="font-size: 14px; opacity: 0.6;"><?= $activeSession ? 'Удачных продаж!' : 'Начните смену, чтобы продавать' ?></span>
        </div>
    </div>

    <div class="kpi-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="margin:0;">📈 Продажи и KPI</h3>
            <button onclick="openPlanModal()" style="background:none; border:1px solid rgba(120,90,255,0.5); color:#785aff; padding:5px 12px; border-radius:10px; cursor:pointer; font-size:12px;">
                <?= round($todayPercent) ?>% плана ✏️
            </button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-box">
                <h4>Сегодня</h4>
                <span class="salary-tag">+<?= number_format($todayStats['total_salary'], 2) ?> L</span>
                <div class="progress-bg"><div class="progress-fill" style="width: <?= $todayPercent ?>%;"></div></div>
                
                <div class="m-line"><span>🧾 Чеков:</span> <b><?= (int)$todayStats['checks'] ?></b></div>
                <div class="m-line"><span>💰 Сумма:</span> <b><?= number_format($todayStats['sum_total'], 2) ?></b></div>
                
                <div class="m-line"><span>📊 Средний чек:</span> <b><?= $todayStats['checks'] > 0 ? number_format($todayStats['sum_total'] / $todayStats['checks'], 2) : '0.00' ?></b></div>
                <div class="m-line"><span>🗓️ Средняя касса:</span> <b><?= number_format($todayStats['sum_total'], 0) ?> L</b></div>
                
                <div class="m-line"><span>🔁 Кросс-продаж:</span> <b><?= (int)$todayStats['cross_sales'] ?></b></div>
                <div class="m-line"><span>📈 Коэф.:</span> <b style="color:#7CFF6B"><?= $todayStats['checks'] > 0 ? round(($todayStats['cross_sales'] / $todayStats['checks']) * 100, 1) : 0 ?>%</b></div>
            </div>

            <div class="kpi-box">
                <h4>За месяц</h4>
                <span class="salary-tag" style="color: #b866ff;">+<?= number_format($monthStats['total_salary'], 2) ?> L</span>
                <div style="height: 8px; margin: 15px 0 5px 0;"></div>
                
                <div class="m-line"><span>🧾 Чеков:</span> <b><?= (int)$monthStats['checks'] ?></b></div>
                <div class="m-line"><span>💰 Сумма:</span> <b><?= number_format($monthStats['sum_total'], 2) ?></b></div>
                
                <div class="m-line"><span>📊 Средний чек:</span> <b><?= $monthStats['checks'] > 0 ? number_format($monthStats['sum_total'] / $monthStats['checks'], 2) : '0.00' ?></b></div>
                
                <div class="m-line"><span>🗓️ Средняя касса:</span> <b><?= $monthStats['active_days'] > 0 ? number_format($monthStats['sum_total'] / $monthStats['active_days'], 0) : '0' ?> L</b></div>

                <div class="m-line"><span>🔁 Кросс-продаж:</span> <b><?= (int)$monthStats['cross_sales'] ?></b></div>
                <div class="m-line"><span>📈 Коэф.:</span> <b style="color:#b866ff"><?= $monthStats['checks'] > 0 ? round(($monthStats['cross_sales'] / $monthStats['checks']) * 100, 1) : 0 ?>%</b></div>
            </div>
        </div>
    </div>
</div>

<div id="planOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; backdrop-filter:blur(5px);">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#1a1a1a; padding:30px; border-radius:25px; border:1px solid #785aff; width:90%; max-width:320px; text-align:center;">
        <h3 style="margin:0 0 20px 0;">Цель на сегодня (L)</h3>
        <input type="number" id="planInput" style="width:100%; background:#222; border:1px solid #333; color:#fff; padding:15px; border-radius:15px; font-size:20px; text-align:center; margin-bottom:20px;" value="<?= $dailyPlan ?>">
        <button onclick="savePlan()" style="width:100%; background:#785aff; color:#fff; border:none; padding:15px; border-radius:15px; font-weight:700; cursor:pointer;">Сохранить</button>
        <button onclick="closePlanModal()" style="background:none; border:none; color:rgba(255,255,255,0.3); margin-top:15px; cursor:pointer;">Закрыть</button>
    </div>
</div>

<script>
function openPlanModal() { document.getElementById('planOverlay').style.display = 'block'; }
function closePlanModal() { document.getElementById('planOverlay').style.display = 'none'; }
function savePlan() {
    const val = document.getElementById('planInput').value;
    const formData = new FormData();
    formData.append('update_plan', '1');
    formData.append('new_plan', val);
    fetch(window.location.href, { method: 'POST', body: formData }).then(() => location.reload());
}
</script>
