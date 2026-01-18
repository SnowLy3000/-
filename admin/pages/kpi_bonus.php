<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/perms.php';

require_auth();

if (!has_role('Admin') && !has_role('Owner')) {
    http_response_code(403);
    exit('Access denied');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* 1. СПРАВОЧНИКИ И НАСТРОЙКИ */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_bonus_%'");
foreach ($stmt as $row) {
    $settings[$row['skey']] = (float)$row['svalue'];
}

function getBonusPercent(float $kpi, $settings): float {
    if ($kpi >= 130) return $settings['kpi_bonus_130'] ?? 30;
    if ($kpi >= 120) return $settings['kpi_bonus_120'] ?? 20;
    if ($kpi >= 110) return $settings['kpi_bonus_110'] ?? 10;
    if ($kpi >= 100) return $settings['kpi_bonus_100'] ?? 0;
    return 0;
}

$branchId = (int)($_GET['branch_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
$from = $month . '-01 00:00:00';
$to   = date('Y-m-t 23:59:59', strtotime($from));

/* 2. ПОЛУЧАЕМ ДАННЫЕ (С УЧЕТОМ АКЦИЙ) */
$rows = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS user_id,
            CONCAT(u.last_name,' ',u.first_name) AS name,
            -- Общий факт продаж
            COALESCE(SUM(s.total_amount),0) AS fact,
            -- ЗП с товаров (уже зафиксирована при продаже)
            (SELECT SUM(si2.salary_amount) 
             FROM sale_items si2 
             JOIN sales s2 ON s2.id = si2.sale_id 
             WHERE s2.user_id = u.id AND s2.branch_id = ? AND s2.created_at BETWEEN ? AND ?
            ) as clean_salary,
            -- Сумма продаж, которые были ПО АКЦИИ (для справки и коррекции)
            COALESCE(SUM(CASE WHEN (
                SELECT COUNT(*) FROM sale_items si3
                JOIN product_promotions pr ON pr.product_name = si3.product_name
                WHERE si3.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
            ) > 0 THEN s.total_amount ELSE 0 END), 0) as promo_fact
        FROM users u
        LEFT JOIN sales s 
          ON s.user_id = u.id
         AND s.branch_id = ?
         AND s.created_at BETWEEN ? AND ?
        WHERE u.status='active'
        GROUP BY u.id
        HAVING fact > 0 OR clean_salary > 0
    ");
    $stmt->execute([$branchId, $from, $to, $branchId, $from, $to]);
    $rows = $stmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id=? AND DATE_FORMAT(month_date,'%Y-%m')=?");
$stmt->execute([$branchId, $month]);
$branchPlan = (float)$stmt->fetchColumn();

$countEmp = count($rows);
$planPerUser = $countEmp ? $branchPlan / $countEmp : 0;
?>

<style>
    .ledger-container { max-width: 1200px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; }
    .compact-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.01); border-radius: 20px; overflow: hidden; }
    .compact-table th { padding: 12px 15px; text-align: left; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); }
    .compact-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }
    
    .col-salary { color: #7CFF6B; font-weight: 700; }
    .col-bonus { color: #ffd166; font-weight: 700; }
    .col-total { background: rgba(120,90,255,0.08); font-weight: 800; }

    .kpi-badge { padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; }
    .status-red { background: rgba(255,107,107,0.1); color: #ff6b6b; }
    .status-yellow { background: rgba(255,209,102,0.1); color: #ffd166; }
    .status-green { background: rgba(124,255,107,0.1); color: #7CFF6B; }

    .promo-info { font-size: 10px; color: #ff4b2b; display: block; margin-top: 2px; font-weight: 600; }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; outline: none; }
</style>

<div class="ledger-container">
    <div style="display:flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 24px;">💰 Расчетная ведомость</h1>
            <p style="margin:5px 0 0 0; opacity:0.5; font-size:14px;">Зарплата и бонусы за <?= h($month) ?></p>
        </div>
        <?php if ($branchId): ?>
        <div style="background: rgba(255,255,255,0.03); padding: 10px 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); text-align: right;">
            <span style="font-size: 9px; text-transform: uppercase; opacity: 0.4; display: block;">План на 1 чел:</span>
            <b style="font-size: 18px;"><?= number_format($planPerUser, 0, '.', ' ') ?> L</b>
        </div>
        <?php endif; ?>
    </div>

    <div style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 18px; margin-bottom: 25px;">
        <form method="get" style="display:flex; gap:12px; align-items: flex-end;">
            <input type="hidden" name="page" value="kpi_bonus">
            <div style="flex: 1;">
                <label style="font-size: 9px; opacity: 0.4; display:block; margin-bottom:5px;">ФИЛИАЛ</label>
                <select name="branch_id" class="st-input" required style="width: 100%;">
                    <option value="">Выберите...</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id']==$branchId?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 9px; opacity: 0.4; display:block; margin-bottom:5px;">МЕСЯЦ</label>
                <input type="month" name="month" class="st-input" value="<?= h($month) ?>">
            </div>
            <button class="btn" style="background:#785aff; border:none; color:#fff; height:38px; padding:0 20px; border-radius:10px; font-weight:700; cursor:pointer;">РАССЧИТАТЬ</button>
        </form>
    </div>

    <?php if ($branchId): ?>
        <div style="background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden;">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Факт продаж</th>
                        <th style="text-align: center;">KPI %</th>
                        <th>Премия %</th>
                        <th>ЗП (Товары)</th>
                        <th>Бонус (План)</th>
                        <th style="text-align: right;">К ВЫДАЧЕ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($rows as $r):
                        $kpi = $planPerUser > 0 ? ($r['fact'] / $planPerUser) * 100 : 0;
                        $bp = getBonusPercent($kpi, $settings);
                        
                        // Логика защиты: премия за план считается от (Общий факт - Акционный факт)
                        // Либо считаем от всего, но ты видишь долю акций.
                        $bonusMoney = $r['fact'] * $bp / 100;
                        $totalToPay = (float)$r['clean_salary'] + $bonusMoney;
                        $grandTotal += $totalToPay;

                        $cls = 'status-red';
                        if ($kpi >= 80) $cls = 'status-yellow';
                        if ($kpi >= 100) $cls = 'status-green';
                    ?>
                    <tr>
                        <td><b><?= h($r['name']) ?></b></td>
                        <td>
                            <b><?= number_format($r['fact'], 0, '.', ' ') ?> L</b>
                            <?php if($r['promo_fact'] > 0): ?>
                                <span class="promo-info">🔥 Акции: <?= number_format($r['promo_fact'], 0, '.', ' ') ?> L</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="kpi-badge <?= $cls ?>"><?= number_format($kpi, 1) ?>%</span>
                        </td>
                        <td><b style="color:#fff"><?= $bp ?>%</b></td>
                        <td class="col-salary"><?= number_format($r['clean_salary'], 0, '.', ' ') ?> L</td>
                        <td class="col-bonus">+<?= number_format($bonusMoney, 0, '.', ' ') ?> L</td>
                        <td class="col-total" style="text-align: right; padding-right: 20px;">
                            <b><?= number_format($totalToPay, 0, '.', ' ') ?> L</b>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(120, 90, 255, 0.05);">
                        <td colspan="6" style="text-align: right; padding: 20px; font-weight: 700; font-size: 11px; opacity: 0.5;">ИТОГО К ВЫПЛАТЕ ПО ФИЛИАЛУ:</td>
                        <td style="text-align: right; padding: 20px; color: #7CFF6B; font-size: 20px; font-weight: 900;"><?= number_format($grandTotal, 0, '.', ' ') ?> L</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>