<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ: доступ по праву просмотра отчетов
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===== ФИЛЬТРЫ ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$branchId = (int)($_GET['branch_id'] ?? 0);
$userId   = (int)($_GET['user_id']   ?? 0);

/* ===== СПРАВОЧНИКИ ===== */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$users_list = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY last_name")->fetchAll();

/* ===== ОСНОВНОЙ ЗАПРОС ===== */
$sql = "
SELECT
    u.id AS user_id,
    u.first_name, u.last_name,
    b.name AS branch_name,
    COUNT(DISTINCT s.id) AS checks,
    SUM(s.total_amount) AS total_sum,
    (SELECT SUM(si2.salary_amount) 
     FROM sale_items si2 
     JOIN sales s2 ON s2.id = si2.sale_id 
     WHERE s2.user_id = u.id 
       AND s2.branch_id = b.id 
       AND DATE(s2.created_at) BETWEEN ? AND ?
    ) AS total_salary,
    COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) >= 2 THEN s.id END) AS cross_sales
FROM sales s
JOIN users u ON u.id = s.user_id
JOIN branches b ON b.id = s.branch_id
WHERE s.total_amount > 0
  AND DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$from, $to, $from, $to];

if ($branchId) { $sql .= " AND s.branch_id = ? "; $params[] = $branchId; }
if ($userId) { $sql .= " AND s.user_id = ? "; $params[] = $userId; }

$sql .= " GROUP BY u.id, b.id ORDER BY total_sum DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<style>
    .report-filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
    .st-input { 
        height: 42px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); 
        border-radius: 12px; padding: 0 12px; color: #fff; outline: none; font-size: 13px; transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }

    .report-table { width: 100%; border-collapse: collapse; }
    .report-table th { 
        text-align: left; padding: 15px; font-size: 10px; text-transform: uppercase; 
        color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 1px;
    }
    .report-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    .report-table tr:hover { background: rgba(255,255,255,0.02); }

    .money { white-space: nowrap; font-weight: 700; }
    .salary-col { color: #7CFF6B; text-shadow: 0 0 10px rgba(124, 255, 107, 0.2); }
    .avg-col { color: #b866ff; }
    
    .badge-cross { background: rgba(184, 102, 255, 0.2); color: #d199ff; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; border: 1px solid rgba(184, 102, 255, 0.3); }
    
    .indicator-bar { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 5px; width: 100%; overflow: hidden; }
    .indicator-fill { height: 100%; background: #785aff; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h1 style="margin:0; font-size: 24px;">📊 Таблица эффективности</h1>
        <p class="muted" style="margin:5px 0 0 0;">Анализ качества продаж и вознаграждений</p>
    </div>
</div>

<div class="card" style="border-radius: 24px; margin-bottom: 20px;">
    <form class="report-filters" method="get">
        <input type="hidden" name="page" value="report_sales">
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Начало</label>
            <input type="date" name="from" class="st-input" value="<?= h($from) ?>">
        </div>
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Конец</label>
            <input type="date" name="to" class="st-input" value="<?= h($to) ?>">
        </div>
        <div>
            <label class="muted" style="font-size: 10px; display:block; margin-bottom:5px; text-transform: uppercase;">Филиал</label>
            <select name="branch_id" class="st-input">
                <option value="0">Все локации</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" style="height: 42px; padding: 0 25px; border-radius: 12px;">Рассчитать</button>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 24px;">
    <div style="overflow-x: auto;">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Филиал</th>
                    <th style="text-align: center;">Чеков</th>
                    <th>Выручка</th>
                    <th style="color: #7CFF6B;">ЗП Бонус</th>
                    <th>Ср. Чек</th>
                    <th>Кросс-чеки</th>
                    <th>Коэф.</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 60px; opacity: 0.3;">За данный период данных нет</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): 
                    $avg = $r['checks'] ? (float)$r['total_sum'] / $r['checks'] : 0;
                    $coef = $r['checks'] ? ((float)$r['cross_sales'] / $r['checks'] * 100) : 0;
                ?>
                <tr>
                    <td>
                        <a href="?page=report_sales_user_chart&user_id=<?= $r['user_id'] ?>" style="text-decoration:none; color:inherit;">
                            <div style="font-weight: 700; font-size: 15px;"><?= h($r['last_name'].' '.mb_substr($r['first_name'],0,1).'.') ?></div>
                            <div style="font-size: 11px; color:#785aff;">Открыть график</div>
                        </a>
                    </td>
                    <td><span class="muted"><?= h($r['branch_name']) ?></span></td>
                    <td style="text-align: center; font-weight: 600;"><?= $r['checks'] ?></td>
                    <td class="money"><?= number_format((float)$r['total_sum'], 0, '.', ' ') ?> L</td>
                    <td class="money salary-col"><?= number_format((float)$r['total_salary'], 2, '.', ' ') ?> L</td>
                    <td class="money avg-col"><?= number_format($avg, 0, '.', ' ') ?> L</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span><?= $r['cross_sales'] ?></span>
                            <?php if ($r['cross_sales'] > 0): ?>
                                <span class="badge-cross">CROSS</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="width: 100px;">
                        <div style="font-weight: 800; color: <?= $coef >= 30 ? '#00c851' : ($coef >= 15 ? '#ffbb33' : '#ff4444') ?>;">
                            <?= round($coef, 1) ?>%
                        </div>
                        <div class="indicator-bar">
                            <div class="indicator-fill" style="width: <?= min($coef, 100) ?>%; background: <?= $coef >= 30 ? '#00c851' : ($coef >= 15 ? '#ffbb33' : '#ff4444') ?>;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
