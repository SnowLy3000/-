<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --- ФИЛЬТРЫ --- */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$branchId = (int)($_GET['branch_id'] ?? 0);

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* --- ЗАПРОС --- */
$sql = "
SELECT 
    DATE(s.created_at) as date,
    COUNT(s.id) as checks_count,
    SUM(s.total_amount) as daily_total,
    SUM(CASE WHEN s.payment_type = 'card' THEN s.total_amount ELSE 0 END) as card_total,
    SUM(CASE WHEN s.payment_type = 'cash' THEN s.total_amount ELSE 0 END) as cash_total,
    SUM(CASE WHEN s.client_id IS NOT NULL THEN 1 ELSE 0 END) as client_checks,
    (SELECT COALESCE(SUM(si2.price * si2.quantity), 0) 
     FROM sale_items si2 
     JOIN sales s2 ON s2.id = si2.sale_id 
     JOIN product_promotions pr ON pr.product_name = si2.product_name
     WHERE DATE(s2.created_at) = DATE(s.created_at) 
     AND DATE(s2.created_at) BETWEEN pr.start_date AND pr.end_date
     " . ($branchId ? " AND s2.branch_id = $branchId" : "") . "
    ) as promo_volume
FROM sales s
WHERE s.total_amount > 0 
  AND DATE(s.created_at) BETWEEN ? AND ?
";

if ($branchId) $sql .= " AND s.branch_id = $branchId";
$sql .= " GROUP BY DATE(s.created_at) ORDER BY DATE(s.created_at) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

$totalRevenue = array_sum(array_column($rows, 'daily_total'));
?>

<style>
    .report-shell { font-family: 'Inter', sans-serif; color: #e1e1e6; max-width: 1200px; margin: 0 auto; }
    
    /* Стеклянные карточки */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-glass { 
        background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
        border: 1px solid rgba(255,255,255,0.08); padding: 25px; border-radius: 24px;
        backdrop-filter: blur(10px);
    }
    .stat-glass span { display: block; font-size: 10px; text-transform: uppercase; color: #82828e; letter-spacing: 1.5px; font-weight: 700; margin-bottom: 8px; }
    .stat-glass b { font-size: 26px; color: #fff; letter-spacing: -1px; }

    /* Фильтры */
    .filter-stripe { 
        background: rgba(0,0,0,0.2); padding: 15px 25px; border-radius: 18px; 
        margin-bottom: 30px; display: flex; gap: 15px; align-items: center; border: 1px solid #222;
    }
    .f-input { height: 40px; background: #000; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 15px; font-size: 13px; outline: none; }
    .f-input:focus { border-color: #785aff; }

    /* Таблица в стиле Fintech */
    .fin-table-wrap { background: #0f0f13; border-radius: 24px; border: 1px solid #1f1f23; overflow: hidden; }
    .fin-table { width: 100%; border-collapse: collapse; }
    .fin-table th { 
        padding: 15px 20px; text-align: left; font-size: 10px; text-transform: uppercase; 
        color: #41414c; background: #16161a; border-bottom: 1px solid #1f1f23;
    }
    .fin-table td { padding: 18px 20px; border-bottom: 1px solid #16161a; font-size: 14px; }
    .fin-table tr:hover { background: rgba(120, 90, 255, 0.03); }

    /* Метки */
    .tag-cash { color: #ffbb33; font-size: 11px; font-weight: 700; }
    .tag-card { color: #0099ff; font-size: 11px; font-weight: 700; }
    .promo-alert { color: #ff4b2b; font-weight: 800; font-size: 14px; }
    .loyalty-val { color: #7CFF6B; font-weight: 800; }
</style>

<div class="report-shell">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="margin:0; font-size: 28px; font-weight: 900; background: linear-gradient(to right, #fff, #82828e); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Отчет по выручке</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #82828e;">Финансовая аналитика за период</p>
        </div>
        <button class="f-input" onclick="window.print()" style="cursor:pointer">🖨️ Печать</button>
    </div>

    <form class="filter-stripe">
        <input type="hidden" name="page" value="report_sales">
        <select name="branch_id" class="f-input" style="flex: 1;">
            <option value="0">Все подразделения</option>
            <?php foreach($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="f-input" value="<?= $from ?>">
        <input type="date" name="to" class="f-input" value="<?= $to ?>">
        <button type="submit" style="background:#785aff; color:#fff; border:none; padding:10px 25px; border-radius:10px; font-weight:700; cursor:pointer;">Применить</button>
    </form>

    <div class="stat-grid">
        <div class="stat-glass"><span>Общий оборот</span><b><?= number_format((float)$totalRevenue, 0, '.', ' ') ?> L</b></div>
        <div class="stat-glass"><span>Дней в периоде</span><b><?= count($rows) ?></b></div>
        <div class="stat-glass"><span>Ср. чек периода</span><b><?= $totalRevenue > 0 ? number_format((float)($totalRevenue / (array_sum(array_column($rows, 'checks_count')) ?: 1)), 0, '.', ' ') : 0 ?> L</b></div>
    </div>

    <div class="fin-table-wrap">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Оплата (Нал / Карта)</th>
                    <th>Акционный объем</th>
                    <th>CRM Лояльность</th>
                    <th style="text-align: right;">Итого за день</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 50px; opacity: 0.3;">Нет данных для отображения</td></tr>
                <?php endif; ?>

                <?php foreach($rows as $r): 
                    $promoVol = (float)($r['promo_volume'] ?? 0);
                    $dailyTotal = (float)($r['daily_total'] ?? 0);
                    $promoShare = $dailyTotal > 0 ? ($promoVol / $dailyTotal * 100) : 0;
                ?>
                <tr>
                    <td>
                        <div style="font-weight: 800; color: #fff;"><?= date('d.m.Y', strtotime($r['date'])) ?></div>
                        <div style="font-size: 11px; color: #41414c;"><?= $r['checks_count'] ?> транзакций</div>
                    </td>
                    <td>
                        <span class="tag-cash">💵 <?= number_format((float)$r['cash_total'], 0, '.', ' ') ?></span>
                        <span style="color:#222; margin: 0 8px;">|</span>
                        <span class="tag-card">💳 <?= number_format((float)$r['card_total'], 0, '.', ' ') ?></span>
                    </td>
                    <td>
                        <div class="<?= $promoShare > 35 ? 'promo-alert' : '' ?>"><?= number_format($promoVol, 0, '.', ' ') ?> L</div>
                        <div style="font-size: 10px; color: #41414c;">Доля акций: <?= round($promoShare, 1) ?>%</div>
                    </td>
                    <td>
                        <div class="loyalty-val"><?= round($r['checks_count'] > 0 ? ($r['client_checks'] / $r['checks_count'] * 100) : 0, 1) ?>%</div>
                        <div style="font-size: 10px; color: #41414c;"><?= $r['client_checks'] ?> клиентов</div>
                    </td>
                    <td style="text-align: right;">
                        <b style="font-size: 18px; color: #fff;"><?= number_format($dailyTotal, 0, '.', ' ') ?> L</b>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>