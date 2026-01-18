<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

$targetUserId = (int)($_GET['user_id'] ?? $_SESSION['user']['id']);

// Если не свой ID, проверяем права администратора
if ($targetUserId !== (int)$_SESSION['user']['id']) {
    require_role('view_reports');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$month = $_GET['month'] ?? date('Y-m');
$monthDate = $month . '-01';

/* 1. ПОЛУЧАЕМ ДАННЫЕ ПОЛЬЗОВАТЕЛЯ И ЕГО ПРОДАЖИ */
$stmt = $pdo->prepare("
    SELECT 
        u.first_name, u.last_name,
        SUM(s.total_amount) as total_fact,
        COUNT(DISTINCT s.id) as total_checks,
        SUM(CASE WHEN s.client_id IS NOT NULL THEN 1 ELSE 0 END) as client_checks,
        -- Считаем акционные чеки
        (SELECT COUNT(DISTINCT s2.id) FROM sales s2 
         WHERE s2.user_id = u.id AND DATE_FORMAT(s2.created_at, '%Y-%m') = ?
         AND EXISTS (
             SELECT 1 FROM sale_items si 
             JOIN product_promotions pr ON pr.product_name = si.product_name 
             WHERE si.sale_id = s2.id AND DATE(s2.created_at) BETWEEN pr.start_date AND pr.end_date
         )
        ) as promo_checks
    FROM users u
    LEFT JOIN sales s ON s.user_id = u.id AND DATE_FORMAT(s.created_at, '%Y-%m') = ?
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$month, $month, $targetUserId]);
$userData = $stmt->fetch();

/* 2. ПОЛУЧАЕМ ПЛАН (средний по филиалам, где работал) */
$stmt = $pdo->prepare("
    SELECT AVG(plan_amount) 
    FROM kpi_plans 
    WHERE month_date = ? 
    AND branch_id IN (SELECT DISTINCT branch_id FROM sales WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?)
");
$stmt->execute([$monthDate, $targetUserId, $month]);
$planAmount = (float)($stmt->fetchColumn() ?: 0);

/* 3. НАСТРОЙКИ ГРЕЙДОВ */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_level_%'");
foreach ($stmt as $row) { $settings[$row['skey']] = $row['svalue']; }

$kpi = ($planAmount > 0) ? ($userData['total_fact'] / $planAmount) * 100 : 0;

// Определяем текущий ранг
$currentRank = 'Стажер';
foreach ([0, 5, 10, 15, 20, 30] as $lvl) {
    if ($kpi >= $lvl) $currentRank = $settings['kpi_level_'.$lvl] ?? $currentRank;
}
?>

<style>
    .kpi-user-container { max-width: 800px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; }
    
    .profile-card { 
        background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(120, 90, 255, 0.2); border-radius: 28px; padding: 30px; margin-bottom: 25px;
    }

    .rank-badge { 
        background: #785aff; color: #fff; padding: 5px 15px; border-radius: 10px; 
        font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    }

    /* Шкала прогресса */
    .progress-box { margin-top: 25px; }
    .progress-labels { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 10px; opacity: 0.5; font-weight: 700; }
    .progress-bar-bg { width: 100%; height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px; overflow: hidden; position: relative; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #785aff, #b866ff); border-radius: 6px; transition: 1s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

    /* Мини-виджеты */
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
    .stat-mini { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 15px; text-align: center; }
    .stat-mini span { display: block; font-size: 9px; opacity: 0.4; text-transform: uppercase; font-weight: 800; margin-bottom: 5px; }
    .stat-mini b { font-size: 18px; }

    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; outline: none; }
</style>

<div class="kpi-user-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 24px; font-weight: 900;">👤 Моя эффективность</h1>
        <form method="get">
            <input type="hidden" name="page" value="kpi_user">
            <input type="month" name="month" class="st-input" value="<?= h($month) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <div class="profile-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <span class="rank-badge"><?= h($currentRank) ?></span>
                <h2 style="margin: 15px 0 5px 0; font-size: 28px; font-weight: 900;"><?= h($userData['first_name'].' '.$userData['last_name']) ?></h2>
                <div style="opacity: 0.4; font-size: 14px;">Выполнение личного плана на этот месяц</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 42px; font-weight: 900; color: #785aff; line-height: 1;"><?= number_format($kpi, 1) ?><small style="font-size: 18px;">%</small></div>
            </div>
        </div>

        <div class="progress-box">
            <div class="progress-labels">
                <span>0%</span>
                <span>Цель: <?= number_format($planAmount, 0, '.', ' ') ?> L</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= min($kpi, 100) ?>%"></div>
            </div>
            <div style="margin-top: 15px; font-size: 13px; font-weight: 600;">
                <?php if($kpi < 100): ?>
                    🔥 Нужно продать еще на <span style="color: #ffd166;"><?= number_format(max(0, $planAmount - $userData['total_fact']), 0, '.', ' ') ?> L</span> до выполнения плана!
                <?php else: ?>
                    🎉 План выполнен! Каждый следующий личный лей увеличивает вашу премию.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-mini">
            <span>Выручка</span>
            <b><?= number_format($userData['total_fact'], 0, '.', ' ') ?> L</b>
        </div>
        <div class="stat-mini">
            <span>Чеков</span>
            <b><?= (int)$userData['total_checks'] ?></b>
        </div>
        <div class="stat-mini">
            <span>Средний чек</span>
            <b><?= $userData['total_checks'] ? number_format($userData['total_fact'] / $userData['total_checks'], 0, '.', ' ') : 0 ?> L</b>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div class="stat-mini" style="border-color: rgba(255, 75, 43, 0.2); text-align: left; display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 24px;">🔥</div>
            <div>
                <span>Продажи по акции</span>
                <b><?= (int)$userData['promo_checks'] ?> чека</b>
                <div style="font-size: 10px; opacity: 0.4;">Доля в обороте: <?= $userData['total_checks'] ? round(($userData['promo_checks'] / $userData['total_checks']) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
        <div class="stat-mini" style="border-color: rgba(124, 255, 107, 0.2); text-align: left; display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 24px;">👤</div>
            <div>
                <span>Работа с клиентами</span>
                <b><?= (int)$userData['client_checks'] ?> чеков</b>
                <div style="font-size: 10px; opacity: 0.4;">Ваша лояльность: <?= $userData['total_checks'] ? round(($userData['client_checks'] / $userData['total_checks']) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; opacity: 0.3; font-size: 12px;">
        Данные обновляются в режиме реального времени после каждого закрытого чека.
    </div>
</div>