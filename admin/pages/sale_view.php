<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ на единое право просмотра продаж
require_role('view_sales');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$saleId = (int)($_GET['sale_id'] ?? 0);
if (!$saleId) exit('Sale not found');

/* ================= ЧЕК ================= */
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.first_name, u.last_name,
           b.name AS branch_name
    FROM sales s
    JOIN users u ON u.id = s.user_id
    JOIN branches b ON b.id = s.branch_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) exit('Sale not found');

/* ================= ТОВАРЫ ================= */
$stmt = $pdo->prepare("
    SELECT *
    FROM sale_items
    WHERE sale_id = ?
");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();

/* ================= РАСЧЁТЫ ================= */
$totalNoDiscount = 0;
$totalWithDiscount = 0;
$totalEconomy = 0;

foreach ($items as &$it) {
    $rowNoDiscount = $it['price'] * $it['quantity'];
    $priceWithDiscount = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
    $rowWithDiscount = $priceWithDiscount * $it['quantity'];

    $it['price_with_discount'] = $priceWithDiscount;
    $it['row_with_discount']   = $rowWithDiscount;
    $it['economy']             = $rowNoDiscount - $rowWithDiscount;

    $totalNoDiscount   += $rowNoDiscount;
    $totalWithDiscount += $rowWithDiscount;
    $totalEconomy      += $it['economy'];
}
unset($it);
?>

<style>
    .receipt-header { text-align: center; border-bottom: 2px dashed rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 20px; }
    .receipt-table { width: 100%; border-collapse: collapse; }
    .receipt-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); padding: 10px 5px; }
    .receipt-table td { padding: 12px 5px; border-top: 1px solid rgba(255,255,255,0.05); }
    
    .discount-badge { background: #ffb300; color: #000; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }
    .economy-text { color: #ffb300; font-weight: 600; }
    
    .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    .info-label { color: rgba(255,255,255,0.4); }
    
    .total-section { background: rgba(255,255,255,0.03); border-radius: 15px; padding: 20px; margin-top: 20px; }
    .grand-total { font-size: 24px; font-weight: 800; color: #fff; }

    .btn-back { display: inline-block; padding: 12px 24px; background: rgba(255,255,255,0.05); color: #fff; text-decoration: none; border-radius: 12px; transition: 0.3s; margin-top: 20px; }
    .btn-back:hover { background: rgba(255,255,255,0.1); }
</style>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="receipt-header">
        <h2 style="margin:0;">Чек №<?= $sale['id'] ?></h2>
        <div class="muted" style="margin-top:5px;">
            <?= date('d.m.Y H:i', strtotime($sale['created_at'])) ?><br>
            Филиал: <b><?= h($sale['branch_name']) ?></b>
        </div>
    </div>

    <div style="padding: 10px 0;">
        <div class="info-row">
            <span class="info-label">Сотрудник:</span>
            <span><?= h($sale['last_name'].' '.$sale['first_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Способ оплаты:</span>
            <span><?= $sale['payment_type'] === 'card' ? '💳 Карта' : '💵 Наличные' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Источник клиента:</span>
            <span><?= h($sale['client_source'] ?: 'Не указан') ?></span>
        </div>
    </div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Товар</th>
                <th style="text-align: center;">Кол-во</th>
                <th style="text-align: right;">Сумма</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <div style="font-weight: 600;"><?= h($it['product_name']) ?></div>
                    <?php if ($it['discount'] > 0): ?>
                        <span class="discount-badge">Скидка <?= $it['discount'] ?>%</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><?= (int)$it['quantity'] ?></td>
                <td style="text-align: right;">
                    <?php if ($it['discount'] > 0): ?>
                        <div style="text-decoration: line-through; font-size: 11px; opacity: 0.4;">
                            <?= number_format($it['price'] * $it['quantity'], 2) ?>
                        </div>
                    <?php endif; ?>
                    <b><?= number_format($it['row_with_discount'], 2) ?> L</b>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="info-row" style="font-size: 13px;">
            <span class="info-label">Сумма без скидки:</span>
            <span><?= number_format($totalNoDiscount, 2) ?> L</span>
        </div>
        <?php if ($totalEconomy > 0): ?>
        <div class="info-row" style="font-size: 13px;">
            <span class="info-label">Суммарная экономия:</span>
            <span class="economy-text">−<?= number_format($totalEconomy, 2) ?> L</span>
        </div>
        <?php endif; ?>
        <div class="info-row" style="margin-top: 10px; align-items: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
            <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Итого к оплате</span>
            <span class="grand-total"><?= number_format($totalWithDiscount, 2) ?> L</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a class="btn-back" href="/admin/index.php?page=sales_all">← Вернуться к списку</a>
    </div>
</div>
