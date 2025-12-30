<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ: Администратор, Владелец или Маркетолог
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== ФИЛЬТРЫ ПЕРИОДА ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

/* ===== СПРАВОЧНИКИ ===== */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* ===== ГЛАВНЫЙ ЗАПРОС АНАЛИТИКИ ===== */
$sql = "
SELECT
    u.id AS user_id, u.first_name, u.last_name,
    b.name AS branch_name,
    COUNT(DISTINCT s.id) AS checks,
    SUM(s.total_amount) AS total_sum,
    -- Расчет зафиксированной ЗП сотрудника
    (SELECT SUM(si2.salary_amount) 
     FROM sale_items si2 
     JOIN sales s2 ON s2.id = si2.sale_id 
     WHERE s2.user_id = u.id AND s2.branch_id = b.id AND DATE(s2.created_at) BETWEEN ? AND ?
    ) AS total_salary,
    -- Кросс-продажи (чеки с 2+ товарами)
    COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales
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

// Считаем общие итоги для инфо-панели
$grandTotalSales = 0;
$grandTotalSalary = 0;
$grandTotalChecks = 0;
foreach($rows as $r) {
    $grandTotalSales += (float)$r['total_sum'];
    $grandTotalSalary += (float)($r['total_salary'] ?? 0);
    $grandTotalChecks += (int)$r['checks'];
}
?>

<style>
    .kpi-wrapper { max-width: 1300px; margin: 0 auto; }
    
    /* Сетка итогов сверху */
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .sum-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 25px; border-radius: 24px; position: relative; }
    .sum-card span { display: block; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); letter-spacing: 1px; margin-bottom: 10px; font-weight: 700; }
    .sum-card b { font-size: 26px; color: #fff; font-weight: 900; }
    .sum-card i { position: absolute; right: 20px; top: 20px; font-style: normal; font-size: 30px; opacity: 0.1; }

    /* Таблица */
    .table-card { background: rgba(255,255,255,0.01); border-radius: 28px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
    .kpi-table { width: 100%; border-collapse: collapse; }
    .kpi-table th { padding: 18px 20px; text-align: left; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.08); }
    .kpi-table td { padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    
    .st-input { height: 46px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 15px; color: #fff; outline: none; }
    .money-green { color: #7CFF6B; font-weight: 800; }
    .money-purple { color: #b866ff; font-weight: 800; }
</style>

<div class="kpi-wrapper">
    <div style="display:flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px;">
        <div>
            <h1 style="margin:0; font-size: 28px;">🎯 Глобальный отчет KPI</h1>
            <p class="muted">Общая статистика продаж и эффективности персонала</p>
        </div>
        <div style="display:flex; gap:10px;">
             <a href="?page=kpi_export_pdf&branch_id=<?= $branchId ?>&from=<?= $from ?>&to=<?= $to ?>" target="_blank" class="btn" style="background:rgba(255,255,255,0.05); padding: 12px 20px; border-radius: 12px;">📄 PDF</a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="sum-card">
            <i>💰</i>
            <span>Общая выручка</span>
            <b><?= number_format($grandTotalSales, 0, '.', ' ') ?> MDL</b>
        </div>
        <div class="sum-card">
            <i>💵</i>
            <span>Фонд зарплат (ЗП)</span>
            <b style="color: #7CFF6B;"><?= number_format($grandTotalSalary, 0, '.', ' ') ?> MDL</b>
        </div>
        <div class="sum-card">
            <i>🧾</i>
            <span>Всего чеков</span>
            <b><?= $grandTotalChecks ?></b>
        </div>
        <div class="sum-card">
            <i>📈</i>
            <span>Средний чек сети</span>
            <b style="color: #b866ff;"><?= $grandTotalChecks ? number_format($grandTotalSales / $grandTotalChecks, 0, '.', ' ') : 0 ?> MDL</b>
        </div>
    </div>

    <div class="card" style="margin-bottom: 30px; border-radius: 20px;">
        <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="kpi">
            <div style="flex: 1; min-width: 200px;">
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px;">ФИЛИАЛ</label>
                <select name="branch_id" class="st-input" style="width: 100%;">
                    <option value="0">Все филиалы</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px;">ПЕРИОД ОТ</label>
                <input type="date" name="from" class="st-input" value="<?= h($from) ?>">
            </div>
            <div>
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px;">ДО</label>
                <input type="date" name="to" class="st-input" value="<?= h($to) ?>">
            </div>
            <button class="btn" style="height: 46px; padding: 0 30px; border-radius: 12px; font-weight: 800;">ОБНОВИТЬ</button>
        </form>
    </div>

    <div class="table-card">
        <table class="kpi-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Филиал</th>
                    <th style="text-align: center;">Чеков</th>
                    <th>Выручка</th>
                    <th>Чистая ЗП</th>
                    <th>Ср. Чек</th>
                    <th>Cross %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 80px; opacity: 0.3;">Нет данных за выбранный период</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): 
                    $avg = $r['checks'] ? (float)$r['total_sum'] / $r['checks'] : 0;
                    $crossPercent = $r['checks'] ? ((float)$r['cross_sales'] / $r['checks'] * 100) : 0;
                ?>
                <tr>
                    <td><b style="font-size: 15px; color: #fff;"><?= h($r['last_name'].' '.$r['first_name']) ?></b></td>
                    <td style="opacity: 0.6; font-size: 12px;"><?= h($r['branch_name']) ?></td>
                    <td style="text-align: center; font-weight: 700;"><?= $r['checks'] ?></td>
                    <td style="font-weight: 800; color: #fff;"><?= number_format($r['total_sum'], 0, '.', ' ') ?> MDL</td>
                    <td class="money-green"><?= number_format((float)($r['total_salary'] ?? 0), 2, '.', ' ') ?> MDL</td>
                    <td class="money-purple"><?= number_format($avg, 0, '.', ' ') ?> MDL</td>
                    <td>
                        <span style="<?= $crossPercent >= 30 ? 'color:#7CFF6B; font-weight:800;' : 'opacity:0.5;' ?>">
                            <?= round($crossPercent, 1) ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
