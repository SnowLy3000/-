<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
// Доступ только для тех, кто имеет право смотреть KPI филиалов
require_role('view_kpi_branch');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$monthKey = date('Y-m', strtotime($from));

/* ===== СПРАВОЧНИКИ И НАСТРОЙКИ ===== */
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

/* ===== KPI ПО СОТРУДНИКАМ ===== */
$rows = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("
    SELECT
        u.id,
        CONCAT(u.last_name,' ',u.first_name) AS name,
        COUNT(DISTINCT s.id) AS checks,
        SUM(s.total_amount) AS total,
        (SELECT SUM(si2.salary_amount) FROM sale_items si2 JOIN sales s2 ON s2.id = si2.sale_id WHERE s2.user_id = u.id AND s2.branch_id = ? AND DATE(s2.created_at) BETWEEN ? AND ?) as total_salary,
        COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales
    FROM users u
    JOIN sales s ON s.user_id = u.id
    WHERE s.branch_id = ? AND s.total_amount > 0 AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY u.id ORDER BY total DESC
    ");
    $stmt->execute([$branchId, $from, $to, $branchId, $from, $to]);
    $rows = $stmt->fetchAll();
}

$planPerUser = count($rows) > 0 ? $branchPlan / count($rows) : 0;

function getKpiColor(float $p): string {
    if ($p >= 100) return 'rgba(124, 255, 107, 0.08)'; // Мягкий зеленый
    if ($p >= 80)  return 'rgba(255, 209, 102, 0.08)'; // Мягкий желтый
    return 'rgba(255, 107, 107, 0.08)';               // Мягкий красный
}
?>

<style>
    .kpi-wrapper { max-width: 1200px; margin: 0 auto; }
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 0 0 24px 24px; }
    .kpi-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .kpi-table th { padding: 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); }
    .kpi-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; white-space: nowrap; }
    
    .st-select, .st-date { height: 46px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 15px; color: #fff; outline: none; transition: 0.3s; }
    .st-select:focus, .st-date:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }
    
    .col-salary { color: #7CFF6B !important; font-weight: 800; }
    .badge-bonus { background: rgba(255, 187, 51, 0.1); color: #ffbb33; padding: 4px 10px; border-radius: 8px; font-weight: 800; }
    
    .export-btns { display: flex; gap: 10px; }
    .btn-outline { border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s; }
    .btn-outline:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); }

    .branch-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .summary-box { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); }
    .summary-box span { display: block; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 8px; }
    .summary-box b { font-size: 20px; color: #fff; }
</style>

<div class="kpi-wrapper">
    <div style="display:flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
        <div>
            <h1 style="margin:0; font-size: 28px;">🏢 Эффективность филиала</h1>
            <p class="muted">Контроль продаж и расходов на персонал</p>
        </div>
        <div class="export-btns">
            <a href="?page=kpi_export_pdf&branch_id=<?= $branchId ?>&from=<?= $from ?>&to=<?= $to ?>" target="_blank" class="btn-outline">📄 PDF Отчет</a>
            <a href="?page=kpi_export_excel&branch_id=<?= $branchId ?>&from=<?= $from ?>&to=<?= $to ?>" class="btn-outline">📊 Excel</a>
        </div>
    </div>

    <div class="card" style="margin-bottom: 25px;">
        <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="kpi_branch">
            <div style="flex: 1; min-width: 200px;">
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px; text-transform: uppercase;">Филиал</label>
                <select name="branch_id" class="st-select" required style="width: 100%;">
                    <option value="">— Выберите филиал —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id']==$branchId?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px; text-transform: uppercase;">Период от</label>
                <input type="date" name="from" class="st-date" value="<?= $from ?>">
            </div>
            <div>
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px; text-transform: uppercase;">До</label>
                <input type="date" name="to" class="st-date" value="<?= $to ?>">
            </div>
            <button class="btn" style="height: 46px; padding: 0 30px; border-radius: 12px;">Показать</button>
        </form>
    </div>

    <?php if ($branchId > 0): ?>
    <div class="branch-summary">
        <div class="summary-box">
            <span>Общий план филиала</span>
            <b><?= number_format($branchPlan, 0, '.', ' ') ?> L</b>
        </div>
        <div class="summary-box">
            <span>План на 1 сотрудника</span>
            <b><?= number_format($planPerUser, 0, '.', ' ') ?> L</b>
        </div>
        <div class="summary-box">
            <span>Сотрудников в периоде</span>
            <b><?= count($rows) ?> чел.</b>
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
        <div class="table-responsive">
            <table class="kpi-table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Выручка</th>
                        <th>ЗП (Чистая)</th>
                        <th>Ср. Чек</th>
                        <th style="text-align: center;">% Плана</th>
                        <th>Cross %</th>
                        <th>Бонус %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalBranchSum = 0;
                    $totalBranchSalary = 0;
                    foreach ($rows as $r):
                        $avg = $r['checks'] ? $r['total'] / $r['checks'] : 0;
                        $percent = ($planPerUser > 0) ? ($r['total'] / $planPerUser) * 100 : 0;
                        $coef = $r['checks'] ? ($r['cross_sales'] / $r['checks'] * 100) : 0;
                        $salary = (float)($r['total_salary'] ?? 0);
                        
                        $totalBranchSum += $r['total'];
                        $totalBranchSalary += $salary;
                        
                        $bonus = 0;
                        if ($percent >= 130) $bonus = $settings['kpi_bonus_130'] ?? 30;
                        elseif ($percent >= 120) $bonus = $settings['kpi_bonus_120'] ?? 20;
                        elseif ($percent >= 110) $bonus = $settings['kpi_bonus_110'] ?? 10;
                    ?>
                    <tr style="background:<?= getKpiColor($percent) ?>">
                        <td><b style="font-size: 15px;"><?= h($r['name']) ?></b></td>
                        <td><b style="color:#fff;"><?= number_format($r['total'], 0, '.', ' ') ?> L</b></td>
                        <td class="col-salary"><?= number_format($salary, 2, '.', ' ') ?> L</td>
                        <td><?= number_format($avg, 0, '.', ' ') ?> L</td>
                        <td style="text-align: center;"><b style="font-size: 16px;"><?= number_format($percent, 1) ?>%</b></td>
                        <td><?= round($coef, 1) ?>%</td>
                        <td><span class="badge-bonus"><?= $bonus ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(120, 90, 255, 0.05);">
                        <td style="font-weight: 800; text-align: right; color: #b866ff;">ИТОГО ПО ФИЛИАЛУ:</td>
                        <td style="font-weight: 800; color: #fff; font-size: 16px;"><?= number_format($totalBranchSum, 0, '.', ' ') ?> L</td>
                        <td style="color: #7CFF6B; font-weight: 800; font-size: 16px;"><?= number_format($totalBranchSalary, 2, '.', ' ') ?> L</td>
                        <td colspan="4" style="text-align: right; color: rgba(255,255,255,0.3); font-size: 12px;">Данные за период: <?= $from ?> - <?= $to ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 100px 20px;">
            <div style="font-size: 50px; opacity: 0.1; margin-bottom: 20px;">🏢</div>
            <h3 class="muted">Пожалуйста, выберите филиал для загрузки данных</h3>
        </div>
    <?php endif; ?>
</div>
