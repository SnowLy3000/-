<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_sales');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

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
        s.id, s.created_at, s.total_amount, s.payment_type, s.is_returned,
        u.first_name, u.last_name, b.name AS branch_name,
        c.name as client_name, c.phone as client_phone,
        (SELECT SUM(si.salary_amount) FROM sale_items si WHERE si.sale_id = s.id AND (si.is_returned = 0 OR si.is_returned IS NULL)) as total_salary,
        (SELECT COUNT(*) FROM sale_items si 
         JOIN product_promotions pr ON pr.product_name = si.product_name 
         WHERE si.sale_id = s.id 
         AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date) as promo_count
    FROM sales s
    JOIN users u     ON u.id = s.user_id
    JOIN branches b  ON b.id = s.branch_id
    LEFT JOIN clients c ON c.id = s.client_id
    WHERE s.created_at BETWEEN :from AND :to
      AND EXISTS (SELECT 1 FROM sale_items WHERE sale_id = s.id)
";

$params = [
    ':from' => $from . ' 00:00:00',
    ':to'   => $to   . ' 23:59:59',
];

if ($userFilter) { $sql .= " AND s.user_id = :uid"; $params[':uid'] = $userFilter; }
if ($branchFilter) { $sql .= " AND s.branch_id = :bid"; $params[':bid'] = $branchFilter; }

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Подсчет итогов
$grandTotal = 0;
$grandSalary = 0;
$promoSalesCount = 0;

foreach($sales as $s) {
    if (!$s['is_returned']) {
        $grandTotal += (float)($s['total_amount'] ?? 0);
        $grandSalary += (float)($s['total_salary'] ?? 0);
        if ($s['promo_count'] > 0) $promoSalesCount++;
    }
}
?>

<style>
    .sales-container { font-family: 'Inter', sans-serif; color: #fff; max-width: 1300px; margin: 0 auto; }
    
    /* СТИЛЬНЫЕ ФИЛЬТРЫ */
    .filter-bar { 
        background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); 
        border-radius: 24px; padding: 20px; margin-bottom: 25px;
    }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: flex-end; }
    .f-label { font-size: 9px; font-weight: 900; color: #41414c; text-transform: uppercase; margin-bottom: 6px; display: block; }
    .st-input { height: 40px; background: #0b0b12; border: 1px solid #1f1f23; border-radius: 12px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; transition: 0.2s; }
    .st-input:focus { border-color: #785aff; }
    .btn-apply { height: 40px; background: #785aff; color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; }

    /* КАРТОЧКИ ИТОГОВ */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-box { background: #0f0f13; border: 1px solid #1f1f23; border-radius: 20px; padding: 20px; position: relative; }
    .stat-box span { font-size: 9px; font-weight: 800; color: #82828e; text-transform: uppercase; letter-spacing: 1px; }
    .stat-box b { font-size: 24px; display: block; margin-top: 5px; letter-spacing: -1px; }

    /* ТАБЛИЦА ПРОДАЖ */
    .table-wrap { background: #0b0b12; border: 1px solid #1f1f23; border-radius: 24px; overflow: hidden; }
    .sales-t { width: 100%; border-collapse: collapse; }
    .sales-t th { padding: 15px; text-align: left; font-size: 9px; text-transform: uppercase; color: #41414c; background: #16161a; }
    .sales-t td { padding: 15px; border-bottom: 1px solid #16161a; font-size: 13px; vertical-align: middle; }
    .sales-t tr:hover { background: rgba(120, 90, 255, 0.02); }

    .badge-promo { display: inline-block; padding: 2px 6px; background: rgba(255, 75, 43, 0.1); color: #ff4b2b; border-radius: 4px; font-size: 9px; font-weight: 900; margin-bottom: 4px; }
    .badge-client { font-size: 11px; color: #b866ff; font-weight: 700; display: block; margin-top: 3px; }
    .ret-row { background: rgba(255, 68, 68, 0.02) !important; opacity: 0.6; }
    .price-strike { text-decoration: line-through; color: #ff4b2b; opacity: 0.5; }
</style>

<div class="sales-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 24px; font-weight: 900;">📜 Журнал операций</h1>
            <p style="margin:0; font-size: 14px; opacity: 0.4;">Детальный мониторинг всех кассовых чеков</p>
        </div>
        <div style="font-size: 12px; font-weight: 700; background: rgba(120, 90, 255, 0.1); color: #785aff; padding: 8px 15px; border-radius: 10px;">
            Всего: <?= count($sales) ?> чеков
        </div>
    </div>

    <form class="filter-bar" method="GET">
        <input type="hidden" name="page" value="sales_all">
        <div class="filter-grid">
            <div class="f-item"><label class="f-label">Период ОТ</label><input type="date" name="from" class="st-input" value="<?= h($from) ?>"></div>
            <div class="f-item"><label class="f-label">Период ДО</label><input type="date" name="to" class="st-input" value="<?= h($to) ?>"></div>
            <div class="f-item">
                <label class="f-label">Филиал</label>
                <select name="branch_id" class="st-input" style="width:100%">
                    <option value="0">Все локации</option>
                    <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= h($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="f-item">
                <label class="f-label">Продавец</label>
                <select name="user_id" class="st-input" style="width:100%">
                    <option value="0">Весь персонал</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $u['id']==$userFilter?'selected':'' ?>><?= h($u['last_name'].' '.$u['first_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn-apply">ОБНОВИТЬ</button>
        </div>
    </form>

    <div class="stats-row">
        <div class="stat-box"><span>Общая выручка</span><b><?= number_format((float)$grandTotal, 0, '.', ' ') ?> L</b></div>
        <div class="stat-box"><span>Бонусы персонала</span><b style="color: #7CFF6B;">+<?= number_format((float)$grandSalary, 2, '.', ' ') ?> L</b></div>
        <div class="stat-box"><span>Акционные чеки</span><b style="color: #ff4b2b;"><?= $promoSalesCount ?></b></div>
        <div class="stat-box"><span>Средний чек</span><b><?= count($sales) ? number_format($grandTotal / count($sales), 0, '.', ' ') : 0 ?> L</b></div>
    </div>

    <div class="table-wrap">
        <table class="sales-t">
            <thead>
                <tr>
                    <th>ID / Время</th>
                    <th>Персонал / Клиент</th>
                    <th>Локация</th>
                    <th>Оплата</th>
                    <th style="text-align: right;">Выручка</th>
                    <th style="text-align: right;">Бонус</th>
                    <th style="text-align: center;">Акт</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): 
                    $isRet = (int)$s['is_returned'] === 1;
                ?>
                <tr class="<?= $isRet ? 'ret-row' : '' ?>">
                    <td>
                        <div style="font-weight: 900; color: #fff;">#<?= $s['id'] ?></div>
                        <div style="font-size: 11px; opacity: 0.3;"><?= date('H:i', strtotime($s['created_at'])) ?> / <?= date('d.m.y', strtotime($s['created_at'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 600;"><?= h($s['last_name']) ?> <?= h(mb_substr($s['first_name'],0,1)) ?>.</div>
                        <?php if($s['client_name']): ?>
                            <span class="badge-client">👤 <?= h($s['client_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="opacity: 0.5;"><?= h($s['branch_name']) ?></td>
                    <td style="font-size: 11px; font-weight: 700; opacity: 0.8;"><?= $s['payment_type'] === 'card' ? '💳 КАРТА' : '💵 НАЛИЧНЫЕ' ?></td>
                    <td style="text-align: right;">
                        <?php if($s['promo_count'] > 0): ?><span class="badge-promo">АКЦИЯ</span><br><?php endif; ?>
                        <b class="<?= $isRet ? 'price-strike' : '' ?>" style="font-size: 15px;">
                            <?= number_format((float)$s['total_amount'], 0, '.', ' ') ?> L
                        </b>
                    </td>
                    <td style="text-align: right; color: #7CFF6B; font-weight: 800;">
                        +<?= number_format((float)$s['total_salary'], 2, '.', ' ') ?>
                    </td>
                    <td style="text-align: center;">
                        <a href="index.php?page=sale_view&sale_id=<?= $s['id'] ?>" style="text-decoration: none; font-size: 18px; filter: grayscale(1); opacity: 0.5;">🔍</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>