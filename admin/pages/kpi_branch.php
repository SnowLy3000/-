<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_kpi_branch');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$monthKey = date('Y-m', strtotime($from));

/* ===== СПРАВОЧНИКИ ===== */
$branches = $pdo->query("SELECT id,name FROM branches ORDER BY name")->fetchAll();
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_bonus_%'");
foreach ($stmt as $row) { $settings[$row['skey']] = (float)$row['svalue']; }

/* ===== ПЛАН ФИЛИАЛА ===== */
$branchPlan = 0;
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id = ? AND DATE_FORMAT(month_date, '%Y-%m') = ?");
    $stmt->execute([$branchId, $monthKey]);
    $branchPlan = (float)$stmt->fetchColumn();
}

/* ===== KPI ПО СОТРУДНИКАМ (С АКЦИЯМИ И КЛИЕНТАМИ) ===== */
$rows = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("
    SELECT
        u.id,
        CONCAT(u.last_name,' ',u.first_name) AS name,
        COUNT(DISTINCT s.id) AS checks,
        SUM(s.total_amount) AS total,
        -- Зарплата
        (SELECT SUM(si2.salary_amount) FROM sale_items si2 JOIN sales s2 ON s2.id = si2.sale_id WHERE s2.user_id = u.id AND s2.branch_id = ? AND DATE(s2.created_at) BETWEEN ? AND ?) as total_salary,
        -- Кросс-продажи
        COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales,
        -- Акционные продажи
        COUNT(DISTINCT CASE WHEN (
            SELECT COUNT(*) FROM sale_items si3
            JOIN product_promotions pr ON pr.product_name = si3.product_name
            WHERE si3.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
        ) > 0 THEN s.id END) AS promo_checks,
        -- Чеки с клиентами
        COUNT(DISTINCT CASE WHEN s.client_id IS NOT NULL THEN s.id END) AS client_checks
    FROM users u
    JOIN sales s ON s.user_id = u.id
    WHERE s.branch_id = ? AND s.total_amount > 0 AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY u.id ORDER BY total DESC
    ");
    $stmt->execute([$branchId, $from, $to, $branchId, $from, $to]);
    $rows = $stmt->fetchAll();
}

$planPerUser = count($rows) > 0 ? $branchPlan / count($rows) : 0;
?>

<style>
    .kb-container { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    
    /* Шапка и фильтры */
    .kb-filter { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 15px 20px; margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-end; }
    .f-item { display: flex; flex-direction: column; gap: 5px; flex: 1; }
    .f-item label { font-size: 9px; font-weight: 800; color: rgba(255,255,255,0.3); text-transform: uppercase; }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    .btn-apply { height: 38px; background: #785aff; color: #fff; border: none; border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; }

    /* Виджеты филиала */
    .kb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .kb-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 18px; }
    .kb-box span { display: block; font-size: 9px; opacity: 0.4; text-transform: uppercase; font-weight: 800; margin-bottom: 5px; }
    .kb-box b { font-size: 18px; letter-spacing: -0.5px; }

    /* Таблица */
    .kb-table-card { background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; }
    .kb-table { width: 100%; border-collapse: collapse; }
    .kb-table th { background: rgba(255,255,255,0.03); padding: 12px 15px; text-align: left; font-size: 9px; color: rgba(255,255,255,0.3); text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .kb-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    .badge-p { background: rgba(120,90,255,0.1); color: #785aff; padding: 3px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; }
    .bar-bg { width: 100%; height: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; margin-top: 6px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 2px; }
</style>

<div class="kb-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin:0; font-size: 22px; font-weight: 900;">🏢 Аналитика филиала</h1>
        <div style="display:flex; gap:8px;">
            <a href="?page=kpi_export_pdf&branch_id=<?= $branchId ?>&from=<?= $from ?>&to=<?= $to ?>" target="_blank" style="font-size: 11px; color: rgba(255,255,255,0.4); text-decoration: none; font-weight: 700; border: 1px solid #333; padding: 8px 15px; border-radius: 10px;">PDF</a>
        </div>
    </div>

    <form class="kb-filter">
        <input type="hidden" name="page" value="kpi_branch">
        <div class="f-item" style="flex:2">
            <label>Выберите филиал</label>
            <select name="branch_id" class="st-input" required style="width: 100%;">
                <option value="">— Локация —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id']==$branchId?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="f-item">
            <label>От</label>
            <input type="date" name="from" class="st-input" value="<?= $from ?>">
        </div>
        <div class="f-item">
            <label>До</label>
            <input type="date" name="to" class="st-input" value="<?= $to ?>">
        </div>
        <button class="btn-apply">Показать</button>
    </form>

    <?php if ($branchId > 0): ?>
    <div class="kb-summary">
        <div class="kb-box"><span>План филиала</span><b><?= number_format($branchPlan, 0, '.', ' ') ?> L</b></div>
        <div class="kb-box"><span>План на 1 чел.</span><b><?= number_format($planPerUser, 0, '.', ' ') ?> L</b></div>
        <div class="kb-box"><span>Выручка сети</span><b><?= number_format(array_sum(array_column($rows, 'total')), 0, '.', ' ') ?> L</b></div>
        <div class="kb-box"><span>Персонал</span><b><?= count($rows) ?> чел.</b></div>
    </div>

    <div class="kb-table-card">
        <table class="kb-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Продажи / ЗП</th>
                    <th>% Плана</th>
                    <th>Средний чек</th>
                    <th>Акция %</th>
                    <th>Лояльность %</th>
                    <th>Бонус %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): 
                    $avg = $r['checks'] ? $r['total'] / $r['checks'] : 0;
                    $percent = ($planPerUser > 0) ? ($r['total'] / $planPerUser) * 100 : 0;
                    $promoP = $r['checks'] ? ($r['promo_checks'] / $r['checks'] * 100) : 0;
                    $clientP = $r['checks'] ? ($r['client_checks'] / $r['checks'] * 100) : 0;
                    
                    $bonus = 0;
                    if ($percent >= 130) $bonus = $settings['kpi_bonus_130'] ?? 30;
                    elseif ($percent >= 120) $bonus = $settings['kpi_bonus_120'] ?? 20;
                    elseif ($percent >= 110) $bonus = $settings['kpi_bonus_110'] ?? 10;
                ?>
                <tr>
                    <td><b style="color: #fff;"><?= h($r['name']) ?></b></td>
                    <td>
                        <div style="font-weight: 700;"><?= number_format($r['total'], 0, '.', ' ') ?> L</div>
                        <div style="font-size: 11px; color: #7CFF6B;">+<?= number_format($r['total_salary'], 0, '.', ' ') ?> L</div>
                    </td>
                    <td>
                        <span class="badge-p"><?= number_format($percent, 1) ?>%</span>
                        <div class="bar-bg"><div class="bar-fill" style="width:<?= min($percent, 100) ?>%; background:#785aff"></div></div>
                    </td>
                    <td><b style="color: #b866ff;"><?= number_format($avg, 0) ?> L</b></td>
                    
                    <td>
                        <span style="font-size: 11px; color: #ff4b2b;"><?= round($promoP, 1) ?>%</span>
                        <div class="bar-bg"><div class="bar-fill" style="width:<?= min($promoP, 100) ?>%; background:#ff4b2b"></div></div>
                    </td>
                    
                    <td>
                        <span style="font-size: 11px; color: #7CFF6B;"><?= round($clientP, 1) ?>%</span>
                        <div class="bar-bg"><div class="bar-fill" style="width:<?= min($clientP, 100) ?>%; background:#7CFF6B"></div></div>
                    </td>

                    <td><b style="color: #ffd166;"><?= $bonus ?>%</b></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div style="text-align: center; padding: 100px 0; opacity: 0.2;">
            <div style="font-size: 40px; margin-bottom: 10px;">🏢</div>
            <h3>Выберите филиал для анализа</h3>
        </div>
    <?php endif; ?>
</div>