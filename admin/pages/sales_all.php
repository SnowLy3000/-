<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ старую проверку на новую систему прав
require_role('view_sales');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ================= ФИЛЬТРЫ ================= */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$userFilter   = (int)($_GET['user_id']   ?? 0);
$branchFilter = (int)($_GET['branch_id'] ?? 0);

/* ================= СПРАВОЧНИКИ ================= */
$users = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY last_name")->fetchAll();
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* ================= ЗАПРОС ПРОДАЖ ================= */
$sql = "
    SELECT
        s.id, s.created_at, s.total_amount, s.payment_type,
        u.first_name, u.last_name, b.name AS branch_name,
        (SELECT SUM(si.salary_amount) FROM sale_items si WHERE si.sale_id = s.id) as total_salary
    FROM sales s
    JOIN users u     ON u.id = s.user_id
    JOIN branches b  ON b.id = s.branch_id
    WHERE s.created_at BETWEEN :from AND :to
";

$params = [
    ':from' => $from . ' 00:00:00',
    ':to'   => $to   . ' 23:59:59',
];

if ($userFilter) {
    $sql .= " AND s.user_id = :uid";
    $params[':uid'] = $userFilter;
}

if ($branchFilter) {
    $sql .= " AND s.branch_id = :bid";
    $params[':bid'] = $branchFilter;
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Подсчет итогов
$grandTotal = 0;
$grandSalary = 0;
foreach($sales as $s) {
    $grandTotal += (float)($s['total_amount'] ?? 0);
    $grandSalary += (float)($s['total_salary'] ?? 0);
}
?>

<style>
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; }
    .st-input { 
        width: 100%; height: 44px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); 
        border-radius: 12px; padding: 0 12px; color: #fff; outline: none; box-sizing: border-box; font-size: 14px;
        transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }
    
    .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .stats-card { 
        background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%); 
        border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 24px; 
    }
    .stats-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: rgba(255,255,255,0.4); font-weight: 700; }
    .stats-val { font-size: 28px; font-weight: 800; color: #fff; margin-top: 8px; }
    .salary-val { color: #7CFF6B; text-shadow: 0 0 15px rgba(124, 255, 107, 0.3); }

    .sales-table { width: 100%; border-collapse: collapse; }
    .sales-table th { text-align: left; padding: 16px; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 1px; }
    .sales-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    .sales-table tr:hover td { background: rgba(120, 90, 255, 0.02); }
    
    .badge-pay { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
    
    .btn-view { 
        background: rgba(255, 255, 255, 0.05); color: #fff; padding: 8px 14px; 
        border-radius: 10px; text-decoration: none; font-size: 13px; border: 1px solid rgba(255,255,255,0.1);
        transition: 0.2s;
    }
    .btn-view:hover { background: #fff; color: #000; }

    .filter-btn {
        background: #785aff; color: #fff; border: none; border-radius: 12px; height: 44px; 
        font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(120, 90, 255, 0.3);
    }
    .filter-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(120, 90, 255, 0.4); }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h1 style="margin:0; font-size: 28px; font-weight: 800;">🧾 Журнал продаж</h1>
        <p class="muted" style="margin: 5px 0 0 0;">Полная история операций и начислений</p>
    </div>
</div>

<div class="card" style="margin-bottom: 20px; border-radius: 24px;">
    <form method="get">
        <input type="hidden" name="page" value="sales_all">
        <div class="report-grid">
            <div>
                <label class="stats-label" style="margin-bottom:8px; display:block;">Период от</label>
                <input type="date" name="from" class="st-input" value="<?= h($from) ?>">
            </div>
            <div>
                <label class="stats-label" style="margin-bottom:8px; display:block;">Период до</label>
                <input type="date" name="to" class="st-input" value="<?= h($to) ?>">
            </div>
            <div>
                <label class="stats-label" style="margin-bottom:8px; display:block;">Локация</label>
                <select name="branch_id" class="st-input">
                    <option value="0">Все филиалы</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="stats-label" style="margin-bottom:8px; display:block;">Сотрудник</label>
                <select name="user_id" class="st-input">
                    <option value="0">Все сотрудники</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id']==$userFilter?'selected':'' ?>><?= h($u['last_name'].' '.$u['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="filter-btn">Применить фильтр</button>
        </div>
    </form>
</div>

<div class="stats-container">
    <div class="stats-card">
        <div class="stats-label">Общий оборот</div>
        <div class="stats-val"><?= number_format((float)$grandTotal, 2, '.', ' ') ?> L</div>
    </div>
    <div class="stats-card">
        <div class="stats-label">Начислено бонусов</div>
        <div class="stats-val salary-val">+ <?= number_format((float)$grandSalary, 2, '.', ' ') ?> L</div>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 24px;">
    <div style="overflow-x: auto;">
        <table class="sales-table">
            <thead>
                <tr>
                    <th>Дата / Время</th>
                    <th>Сотрудник</th>
                    <th>Филиал</th>
                    <th>Тип оплаты</th>
                    <th>Выручка</th>
                    <th style="color: #7CFF6B;">Бонус</th>
                    <th style="text-align: right;">Детали</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$sales): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 60px; opacity: 0.3;">За выбранный период данных нет</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td style="font-weight: 600; white-space: nowrap;">
                            <?= date('d.m.Y', strtotime($s['created_at'])) ?>
                            <span class="muted" style="font-weight: 400; font-size: 12px; margin-left: 5px;"><?= date('H:i', strtotime($s['created_at'])) ?></span>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:24px; height:24px; background:rgba(120,90,255,0.2); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:10px; color:#b866ff; font-weight:800;">
                                    <?= mb_substr($s['first_name'],0,1) ?>
                                </div>
                                <b><?= h($s['last_name']) ?></b>
                            </div>
                        </td>
                        <td><span class="muted"><?= h($s['branch_name']) ?></span></td>
                        <td>
                            <?php if ($s['payment_type'] === 'card'): ?>
                                <span class="badge-pay" style="background: rgba(0, 153, 255, 0.1); color: #0099ff;">💳 Карта</span>
                            <?php else: ?>
                                <span class="badge-pay" style="background: rgba(255, 187, 51, 0.1); color: #ffbb33;">💵 Наличные</span>
                            <?php endif; ?>
                        </td>
                        <td><b style="font-size: 15px;"><?= number_format((float)($s['total_amount'] ?? 0), 2, '.', ' ') ?> L</b></td>
                        <td><span style="color: #7CFF6B; font-weight: 700;">+<?= number_format((float)($s['total_salary'] ?? 0), 2, '.', ' ') ?></span></td>
                        <td style="text-align: right;">
                            <a class="btn-view" href="/admin/index.php?page=sale_view&sale_id=<?= $s['id'] ?>">Просмотр</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
