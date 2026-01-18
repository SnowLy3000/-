<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ===== ФИЛЬТРЫ ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* ===== ОБНОВЛЕННЫЙ ЗАПРОС КPI ===== */
$sql = "
SELECT
    u.id AS user_id, u.first_name, u.last_name,
    b.name AS branch_name,
    COUNT(DISTINCT s.id) AS checks,
    SUM(s.total_amount) AS total_sum,
    -- Зарплата
    (SELECT SUM(si2.salary_amount) 
     FROM sale_items si2 
     JOIN sales s2 ON s2.id = si2.sale_id 
     WHERE s2.user_id = u.id AND s2.branch_id = b.id AND DATE(s2.created_at) BETWEEN ? AND ?
    ) AS total_salary,
    -- Кросс-продажи (2+ товара)
    COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales,
    -- Акционные продажи (чеки, где был хоть 1 промо-товар)
    COUNT(DISTINCT CASE WHEN (
        SELECT COUNT(*) FROM sale_items si3
        JOIN product_promotions pr ON pr.product_name = si3.product_name
        WHERE si3.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
    ) > 0 THEN s.id END) AS promo_checks,
    -- Постоянные клиенты (чеки, где указан клиент)
    COUNT(DISTINCT CASE WHEN s.client_id IS NOT NULL THEN s.id END) AS client_checks
FROM sales s
JOIN users u ON u.id = s.user_id
JOIN branches b ON b.id = s.branch_id
WHERE s.total_amount > 0 AND DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$from, $to, $from, $to];
if ($branchId) { $sql .= " AND s.branch_id = ? "; $params[] = $branchId; }
$sql .= " GROUP BY u.id, b.id ORDER BY total_sum DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Итоги
$grandTotalSales = 0; $grandTotalSalary = 0; $grandTotalChecks = 0;
foreach($rows as $r) {
    $grandTotalSales += (float)$r['total_sum'];
    $grandTotalSalary += (float)($r['total_salary'] ?? 0);
    $grandTotalChecks += (int)$r['checks'];
}
?>

<style>
    .kpi-container { font-family: 'Inter', sans-serif; color: #fff; }
    
    /* Компактные виджеты */
    .kpi-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .kpi-stat-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 18px; border-radius: 20px; }
    .kpi-stat-card span { display: block; font-size: 9px; text-transform: uppercase; color: rgba(255,255,255,0.4); letter-spacing: 1px; font-weight: 800; }
    .kpi-stat-card b { font-size: 20px; display: block; margin-top: 5px; }

    /* Компактный фильтр */
    .kpi-filter { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 15px; margin-bottom: 25px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
    .f-item { display: flex; flex-direction: column; gap: 5px; }
    .f-item label { font-size: 9px; font-weight: 800; color: rgba(255,255,255,0.3); }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    .btn-update { height: 38px; background: #785aff; color: #fff; border: none; border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; }

    /* Таблица */
    .kpi-table-box { background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; }
    .kpi-table { width: 100%; border-collapse: collapse; }
    .kpi-table th { background: rgba(255,255,255,0.03); padding: 12px 15px; text-align: left; font-size: 9px; text-transform: uppercase; color: rgba(255,255,255,0.3); letter-spacing: 0.5px; }
    .kpi-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    .prog-bar-bg { width: 100%; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; margin-top: 5px; overflow: hidden; }
    .prog-bar-fill { height: 100%; border-radius: 2px; }
</style>

<div class="kpi-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 24px; font-weight: 900;">🎯 Аналитика KPI</h1>
        <a href="?page=kpi_export_pdf&branch_id=<?= $branchId ?>&from=<?= $from ?>&to=<?= $to ?>" target="_blank" style="font-size: 12px; color: #785aff; text-decoration: none; font-weight: 700;">СКАЧАТЬ PDF</a>
    </div>

    <div class="kpi-summary">
        <div class="kpi-stat-card"><span>Выручка</span><b><?= number_format($grandTotalSales, 0, '.', ' ') ?> MDL</b></div>
        <div class="kpi-stat-card"><span>Зарплатный фонд</span><b style="color: #7CFF6B;"><?= number_format($grandTotalSalary, 0, '.', ' ') ?> MDL</b></div>
        <div class="kpi-stat-card"><span>Средний чек</span><b style="color: #b866ff;"><?= $grandTotalChecks ? number_format($grandTotalSales / $grandTotalChecks, 0, '.', ' ') : 0 ?> MDL</b></div>
        <div class="kpi-stat-card"><span>Чеков</span><b><?= $grandTotalChecks ?></b></div>
    </div>

    <form class="kpi-filter">
        <input type="hidden" name="page" value="kpi">
        <div class="f-item" style="flex:1">
            <label>ЛОКАЦИЯ</label>
            <select name="branch_id" class="st-input" style="width:100%">
                <option value="0">Все филиалы</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="f-item">
            <label>ОТ</label>
            <input type="date" name="from" class="st-input" value="<?= h($from) ?>">
        </div>
        <div class="f-item">
            <label>ДО</label>
            <input type="date" name="to" class="st-input" value="<?= h($to) ?>">
        </div>
        <button class="btn-update">ОБНОВИТЬ</button>
    </form>

    <div class="kpi-table-box">
        <table class="kpi-table">
            <thead>
                <tr>
                    <th>Сотрудник / Филиал</th>
                    <th>Чеки</th>
                    <th>Выручка / ЗП</th>
                    <th>Средний чек</th>
                    <th>Cross %</th>
                    <th>Акция %</th>
                    <th>Лояльность %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): 
                    $avg = $r['checks'] ? (float)$r['total_sum'] / $r['checks'] : 0;
                    $crossP = $r['checks'] ? ($r['cross_sales'] / $r['checks'] * 100) : 0;
                    $promoP = $r['checks'] ? ($r['promo_checks'] / $r['checks'] * 100) : 0;
                    $clientP = $r['checks'] ? ($r['client_checks'] / $r['checks'] * 100) : 0;
                ?>
                <tr>
                    <td>
                        <b style="color: #fff;"><?= h($r['last_name'].' '.$r['first_name']) ?></b><br>
                        <small style="opacity: 0.4; font-size: 10px;"><?= h($r['branch_name']) ?></small>
                    </td>
                    <td style="font-weight: 700; opacity: 0.8;"><?= $r['checks'] ?></td>
                    <td>
                        <div style="font-weight: 800;"><?= number_format($r['total_sum'], 0, '.', ' ') ?> MDL</div>
                        <div style="color: #7CFF6B; font-size: 11px;">+<?= number_format($r['total_salary'] ?? 0, 0, '.', ' ') ?> MDL</div>
                    </td>
                    <td style="color: #b866ff; font-weight: 700;"><?= number_format($avg, 0, '.', ' ') ?> MDL</td>
                    
                    <td>
                        <span style="font-size: 11px;"><?= round($crossP, 1) ?>%</span>
                        <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= min($crossP, 100) ?>%; background:#785aff"></div></div>
                    </td>
                    
                    <td>
                        <span style="font-size: 11px; color: #ff4b2b;"><?= round($promoP, 1) ?>%</span>
                        <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= min($promoP, 100) ?>%; background:#ff4b2b"></div></div>
                    </td>

                    <td>
                        <span style="font-size: 11px; color: #7CFF6B;"><?= round($clientP, 1) ?>%</span>
                        <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= min($clientP, 100) ?>%; background:#7CFF6B"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>