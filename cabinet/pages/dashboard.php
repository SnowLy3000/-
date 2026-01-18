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

/* === ЛОГИКА ДИСЦИПЛИНЫ (ШТРАФЫ) === */
$late_fine_enabled = setting('late_fine_enabled', '0');      // Глобальный вкл/выкл штрафа
$show_late_setting = setting('show_late_on_dashboard', '0'); // Вкл/выкл блока на главном экране
$show_money        = setting('late_fine_show_money', '1');   // Показывать ли сумму MDL
$fine_per_min      = (float)setting('late_fine_per_minute', '0');

$my_late_minutes = 0;
$my_total_fine = 0;

// Блок сработает только если ВКЛЮЧЕНО отображение
if ($show_late_setting === '1') {
    $stmtLate = $pdo->prepare("SELECT SUM(late_minutes) FROM shift_sessions WHERE user_id = ? AND checkin_at >= ?");
    $stmtLate->execute([$userId, $monthStart]);
    $my_late_minutes = (int)$stmtLate->fetchColumn();
    
    // Считаем деньги, только если штрафы ВКЛЮЧЕНЫ глобально и включен показ денег
    if ($late_fine_enabled === '1' && $show_money === '1') {
        $my_total_fine = $my_late_minutes * $fine_per_min;
    }
}

/* === ЛОГИКА ПРОВЕРКИ ПЕРЕОЦЕНКИ === */
$stmtPos = $pdo->prepare("SELECT position_id FROM user_positions WHERE user_id = ? LIMIT 1");
$stmtPos->execute([$userId]);
$u_pos_id = (int)$stmtPos->fetchColumn();

$stmtActs = $pdo->query("SELECT id, target_positions FROM price_revaluations WHERE created_at > NOW() - INTERVAL 1 DAY");
$recent_acts = $stmtActs->fetchAll();

$pending_reval_id = 0;
foreach ($recent_acts as $act) {
    $targets = json_decode((string)$act['target_positions'], true);
    if (empty($targets) || in_array($u_pos_id, $targets)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM price_revaluation_confirmations WHERE revaluation_id = ? AND user_id = ?");
        $stmtCheck->execute([$act['id'], $userId]);
        if (!$stmtCheck->fetch()) {
            $pending_reval_id = (int)$act['id'];
            break; 
        }
    }
}

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
            COUNT(CASE WHEN s.is_returned = 0 THEN 1 END) AS checks, 
            COALESCE(SUM(CASE WHEN s.is_returned = 0 THEN s.total_amount ELSE 0 END), 0) AS sum_total,
            COALESCE(SUM(CASE WHEN s.is_returned = 1 THEN s.total_amount ELSE 0 END), 0) AS sum_returned,
            (SELECT COALESCE(SUM(si.salary_amount), 0) 
             FROM sale_items si 
             JOIN sales s2 ON s2.id = si.sale_id 
             WHERE s2.user_id = ? AND s2.created_at BETWEEN ? AND ? AND s2.is_returned = 0) as total_salary,
            (SELECT COALESCE(SUM(si.salary_amount), 0) 
             FROM sale_items si 
             JOIN sales s2 ON s2.id = si.sale_id 
             WHERE s2.user_id = ? AND s2.created_at BETWEEN ? AND ? AND s2.is_returned = 1) as salary_returned,
            COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 AND s.is_returned = 0 THEN s.id END) AS cross_sales,
            COUNT(DISTINCT DATE(s.created_at)) as active_days
        FROM sales s 
        WHERE s.user_id = ? AND s.created_at BETWEEN ? AND ? AND s.total_amount > 0
    ");
    $stmt->execute([$userId, $from, $to, $userId, $from, $to, $userId, $from, $to]);
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

$stmt = $pdo->prepare("SELECT daily_plan_kpi FROM users WHERE id = ?");
$stmt->execute([$userId]);
$dailyPlan = (float)($stmt->fetchColumn() ?: 5000);
$todayPercent = $dailyPlan > 0 ? min(100, ($todayStats['sum_total'] / $dailyPlan) * 100) : 0;
?>

<style>
    .dashboard { font-family: 'Inter', sans-serif; max-width: 900px; margin: 0 auto; color: #fff; }
    .welcome-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 0 10px; }
    
    .reval-alert { 
        background: linear-gradient(90deg, rgba(120, 90, 255, 0.2), rgba(120, 90, 255, 0.05)); 
        border: 1px solid #785aff; border-radius: 20px; padding: 20px; 
        margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;
        animation: pulse-border 2s infinite;
    }
    @keyframes pulse-border { 
        0% { box-shadow: 0 0 0 0 rgba(120, 90, 255, 0.4); } 
        70% { box-shadow: 0 0 0 10px rgba(120, 90, 255, 0); } 
        100% { box-shadow: 0 0 0 0 rgba(120, 90, 255, 0); } 
    }

    .status-card { background: linear-gradient(135deg, rgba(120,90,255,0.2) 0%, rgba(120,90,255,0.05) 100%); border: 1px solid rgba(120,90,255,0.3); border-radius: 24px; padding: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 25px; }
    .kpi-container { background: rgba(255,255,255,0.03); border-radius: 24px; padding: 25px; border: 1px solid rgba(255,255,255,0.05); }
    .kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kpi-box { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.03); }
    .kpi-box h4 { margin: 0 0 15px 0; font-size: 11px; text-transform: uppercase; color: #785aff; letter-spacing: 1.5px; font-weight: 700; border-bottom: 1px solid rgba(120,90,255,0.2); padding-bottom: 8px;}
    
    .salary-tag { font-size: 22px; font-weight: 800; color: #7CFF6B; display: inline-flex; align-items: flex-start; margin-bottom: 10px; }
    .minus-sup { color: #ff6b6b; font-size: 11px; font-weight: 800; margin-left: 3px; transform: translateY(-3px); }

    .progress-bg { height: 8px; background: rgba(255,255,255,0.05); border-radius: 10px; margin: 15px 0 5px 0; overflow: hidden; }
    .progress-fill { height: 100%; background: #785aff; border-radius: 10px; transition: 1s ease-out; }
    .m-line { display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
    .m-line span { color: rgba(255,255,255,0.5); }
    .m-line b { color: #fff; display: flex; align-items: flex-start; }
    @media (max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

    /* Стиль блока опозданий */
    .late-alert {
        background: rgba(255, 68, 68, 0.1); border: 1px solid rgba(255, 68, 68, 0.2); 
        padding: 20px; border-radius: 20px; margin-bottom: 25px; 
        display: flex; justify-content: space-between; align-items: center;
    }
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

<?php if ($show_late_setting === '1' && $my_late_minutes > 0): ?>
<div class="late-alert">
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="font-size: 24px;">⏰</div>
        <div>
            <b style="color: #ff6b6b; display: block;">Дисциплина (этот месяц)</b>
            <span style="opacity: 0.7; font-size: 13px;">Опозданий на <?= $my_late_minutes ?> мин.</span>
        </div>
    </div>
    
    <?php if ($late_fine_enabled === '1' && $show_money === '1' && $my_total_fine > 0): ?>
    <div style="text-align: right;">
        <b style="font-size: 18px; color: #ff6b6b;">- <?= number_format($my_total_fine, 2) ?> MDL</b>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <?php if ($pending_reval_id > 0): ?>
    <div class="reval-alert">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 30px;">🏷️</div>
            <div>
                <b style="color: #fff; font-size: 16px; display: block;">Обновление цен!</b>
                <span style="opacity: 0.7; font-size: 13px;">Необходимо ознакомиться с актом №<?= $pending_reval_id ?></span>
            </div>
        </div>
        <a href="?page=price_confirm&id=<?= $pending_reval_id ?>" style="background: #785aff; color: #fff; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 13px;">
            ОТКРЫТЬ
        </a>
    </div>
    <?php endif; ?>

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
                <div class="salary-tag">
                    +<?= number_format($todayStats['total_salary'], 2) ?> L
                    <?php if($todayStats['salary_returned'] > 0): ?>
                        <sup class="minus-sup">(-<?= round($todayStats['salary_returned']) ?>)</sup>
                    <?php endif; ?>
                </div>
                
                <div class="progress-bg"><div class="progress-fill" style="width: <?= $todayPercent ?>%;"></div></div>
                
                <div class="m-line"><span>🧾 Чеков:</span> <b><?= (int)$todayStats['checks'] ?></b></div>
                <div class="m-line">
                    <span>💰 Сумма:</span> 
                    <b>
                        <?= number_format($todayStats['sum_total'], 2) ?>
                        <?php if($todayStats['sum_returned'] > 0): ?>
                            <sup class="minus-sup">(-<?= round($todayStats['sum_returned']) ?>)</sup>
                        <?php endif; ?>
                    </b>
                </div>
                
                <div class="m-line"><span>📊 Средний чек:</span> <b><?= $todayStats['checks'] > 0 ? number_format($todayStats['sum_total'] / $todayStats['checks'], 2) : '0.00' ?></b></div>
                <div class="m-line"><span>🗓️ Средняя касса:</span> <b><?= number_format($todayStats['sum_total'], 0) ?> L</b></div>
                
                <div class="m-line"><span>🔁 Кросс-продаж:</span> <b><?= (int)$todayStats['cross_sales'] ?></b></div>
                <div class="m-line"><span>📈 Коэф.:</span> <b style="color:#7CFF6B"><?= $todayStats['checks'] > 0 ? round(($todayStats['cross_sales'] / $todayStats['checks']) * 100, 1) : 0 ?>%</b></div>
            </div>

            <div class="kpi-box">
                <h4>За месяц</h4>
                <div class="salary-tag" style="color: #b866ff;">
                    +<?= number_format($monthStats['total_salary'], 2) ?> L
                    <?php if($monthStats['salary_returned'] > 0): ?>
                        <sup class="minus-sup">(-<?= round($monthStats['salary_returned']) ?>)</sup>
                    <?php endif; ?>
                </div>
                
                <div style="height: 8px; margin: 15px 0 5px 0;"></div>
                
                <div class="m-line"><span>🧾 Чеков:</span> <b><?= (int)$monthStats['checks'] ?></b></div>
                <div class="m-line">
                    <span>💰 Сумма:</span> 
                    <b>
                        <?= number_format($monthStats['sum_total'], 2) ?>
                        <?php if($monthStats['sum_returned'] > 0): ?>
                            <sup class="minus-sup">(-<?= round($monthStats['sum_returned']) ?>)</sup>
                        <?php endif; ?>
                    </b>
                </div>
                
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
