<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ: либо админ, либо просмотр своего KPI
require_role('view_kpi_user');

$isAdmin = has_role('Admin') || has_role('Owner');
$currentUser = current_user();

// Защита: если не админ, принудительно ставим ID текущего пользователя
$userId = (int)($_GET['user_id'] ?? $currentUser['id']);

if (!$isAdmin && $userId !== (int)$currentUser['id']) {
    // Если обычный юзер пытается подсмотреть чужой ID в ссылке - сбрасываем на его собственный
    $userId = (int)$currentUser['id'];
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== СПИСОК СОТРУДНИКОВ (для фильтра админа) ===== */
$allUsers = [];
if ($isAdmin) {
    $allUsers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status='active' ORDER BY last_name")->fetchAll();
}

/* ===== ПЕРИОД ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$monthKey = date('Y-m', strtotime($from));

/* ===== 1. ДАННЫЕ ПОЛЬЗОВАТЕЛЯ И ЕГО ФИЛИАЛ ===== */
$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, b.id as branch_id, b.name as branch_name
    FROM users u
    LEFT JOIN branches b ON b.id = (SELECT branch_id FROM sales WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1)
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$uData = $stmt->fetch();
$userName = $uData ? $uData['first_name'].' '.$uData['last_name'] : 'Неизвестно';
$branchId = (int)($uData['branch_id'] ?? 0);

/* ===== 2. РАСЧЕТ ПЛАНА ===== */
$personalPlan = 0;
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id = ? AND DATE_FORMAT(month_date, '%Y-%m') = ?");
    $stmt->execute([$branchId, $monthKey]);
    $branchTotalPlan = (float)$stmt->fetchColumn();

    // Считаем сколько людей реально торговало на этом филиале в этом месяце
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM sales WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$branchId, $from, $to]);
    $empCount = (int)$stmt->fetchColumn() ?: 1;

    $personalPlan = $branchTotalPlan / $empCount;
}

/* ===== 3. KPI ЗАПРОС (ПРОДАЖИ + ЗП) ===== */
$stmt = $pdo->prepare("
SELECT
    COUNT(DISTINCT s.id) AS checks,
    SUM(s.total_amount) AS sum_total,
    (SELECT SUM(si2.salary_amount) FROM sale_items si2 JOIN sales s2 ON s2.id = si2.sale_id WHERE s2.user_id = ? AND DATE(s2.created_at) BETWEEN ? AND ?) as total_salary,
    COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales
FROM sales s
WHERE s.user_id = ? AND s.total_amount > 0 AND DATE(s.created_at) BETWEEN ? AND ?
");
$stmt->execute([$userId, $from, $to, $userId, $from, $to]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

$checks = (int)$kpi['checks'];
$sum    = (float)$kpi['sum_total'];
$salary = (float)($kpi['total_salary'] ?? 0);
$cross  = (int)$kpi['cross_sales'];
$avg    = $checks ? $sum / $checks : 0;
$coef   = $checks ? ($cross / $checks) * 100 : 0;
$planPercent = $personalPlan > 0 ? ($sum / $personalPlan) * 100 : 0;
?>

<style>
    .kpi-container { max-width: 1100px; margin: 0 auto; }
    .st-input { height: 44px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 15px; color: #fff; outline: none; font-size: 14px; transition: 0.3s; }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }

    .kpi-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 25px; }
    
    .kpi-card { 
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%); 
        border: 1px solid rgba(255,255,255,0.08); padding: 25px; border-radius: 28px; 
        position: relative; overflow: hidden;
    }
    .kpi-card::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(120,90,255,0.05) 0%, transparent 70%); pointer-events: none; }
    
    .kpi-card span { display: block; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); letter-spacing: 1.5px; margin-bottom: 10px; font-weight: 700; }
    .kpi-card b { display: block; font-size: 26px; color: #fff; font-weight: 900; }
    .kpi-card .icon { position: absolute; top: 20px; right: 20px; font-size: 24px; opacity: 0.2; }

    .salary-highlight { border: 1px solid rgba(124, 255, 107, 0.2); background: rgba(124, 255, 107, 0.03); }
    .salary-highlight b { color: #7CFF6B; text-shadow: 0 0 15px rgba(124, 255, 107, 0.3); }

    .plan-section { margin-top: 30px; padding: 30px; background: rgba(120, 90, 255, 0.03); border-radius: 32px; border: 1px solid rgba(120, 90, 255, 0.1); }
    .progress-wrapper { height: 16px; background: rgba(255,255,255,0.05); border-radius: 20px; margin-top: 20px; padding: 4px; border: 1px solid rgba(255,255,255,0.05); }
    .progress-fill { height: 100%; border-radius: 20px; background: linear-gradient(90deg, #785aff, #b866ff); box-shadow: 0 0 20px rgba(120, 90, 255, 0.5); transition: width 1s cubic-bezier(0.17, 0.67, 0.83, 0.67); }
    
    .filter-bar { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; margin-top: 25px; background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); }
</style>

<div class="kpi-container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin:0; font-size: 28px;">🎯 Личная эффективность</h1>
            <p class="muted" style="margin:5px 0 0 0;">Сотрудник: <span style="color:#b866ff; font-weight:700;"><?= h($userName) ?></span> • <?= h($uData['branch_name'] ?? 'Без привязки') ?></p>
        </div>
    </div>

    <form method="get" class="filter-bar">
        <input type="hidden" name="page" value="kpi_user">
        <?php if ($isAdmin): ?>
            <div style="flex: 1; min-width: 200px;">
                <label class="muted" style="font-size: 10px; display:block; margin-bottom:8px; text-transform: uppercase;">Сменить сотрудника</label>
                <select name="user_id" class="st-input" style="width: 100%;">
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id']==$userId ? 'selected' : '' ?>>
                            <?= h($u['last_name'].' '.$u['first_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:8px; text-transform: uppercase;">От</label>
            <input type="date" name="from" class="st-input" value="<?= $from ?>">
        </div>
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:8px; text-transform: uppercase;">До</label>
            <input type="date" name="to" class="st-input" value="<?= $to ?>">
        </div>
        <button class="btn" style="height: 44px; padding: 0 30px; border-radius: 12px;">Обновить</button>
    </form>

    <div class="kpi-stat-grid">
        <div class="kpi-card salary-highlight">
            <div class="icon">💰</div>
            <span>Бонусы к выплате</span>
            <b><?= number_format($salary, 2, '.', ' ') ?> L</b>
        </div>
        <div class="kpi-card">
            <div class="icon">📈</div>
            <span>Общая выручка</span>
            <b><?= number_format($sum, 0, '.', ' ') ?> L</b>
        </div>
        <div class="kpi-card">
            <div class="icon">🛒</div>
            <span>Средний чек</span>
            <b><?= number_format($avg, 0, '.', ' ') ?> L</b>
        </div>
        <div class="kpi-card">
            <div class="icon">🔄</div>
            <span>Коэф. Cross-sell</span>
            <b><?= number_format($coef, 1) ?>%</b>
        </div>
        <div class="kpi-card">
            <div class="icon">🧾</div>
            <span>Всего чеков</span>
            <b><?= $checks ?></b>
        </div>
        <div class="kpi-card" style="<?= $planPercent >= 100 ? 'border-color: #4ade80;' : '' ?>">
            <div class="icon">🎯</div>
            <span>Выполнение плана</span>
            <b style="<?= $planPercent >= 100 ? 'color: #4ade80;' : '' ?>"><?= number_format($planPercent, 1) ?>%</b>
        </div>
    </div>

    <div class="plan-section">
        <div style="display:flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Прогресс выполнения плана</span>
                <div style="font-size: 16px; margin-top: 10px; color: rgba(255,255,255,0.8);">
                    Цель на период: <b style="color:#fff;"><?= number_format($personalPlan, 0, '.', ' ') ?> L</b>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 32px; font-weight: 900; color: #fff; line-height: 1;"><?= number_format($planPercent, 1) ?>%</div>
                <div class="muted" style="font-size: 10px; margin-top: 5px;">ДО ЗАВЕРШЕНИЯ: <?= number_format(max(0, $personalPlan - $sum), 0, '.', ' ') ?> L</div>
            </div>
        </div>
        <div class="progress-wrapper">
            <div class="progress-fill" style="width: <?= min(100, $planPercent) ?>%; <?= $planPercent >= 100 ? 'background: linear-gradient(90deg, #2ecc71, #4ade80); box-shadow: 0 0 20px rgba(74, 222, 128, 0.4);' : '' ?>"></div>
        </div>
    </div>
</div>
