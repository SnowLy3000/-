<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('sale_view');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$saleId = (int)($_GET['sale_id'] ?? 0);
if (!$saleId) exit('Чек не найден');

/* ================= ПОЛУЧАЕМ ЧЕК + ДАННЫЕ КЛИЕНТА + ВОЗВРАТА ================= */
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.first_name, u.last_name,
           b.name AS branch_name,
           c.name as client_name, c.phone as client_phone,
           r.return_1c_id, r.reason, r.defect_description,
           u_ret.first_name as ret_f_name, u_ret.last_name as ret_l_name
    FROM sales s
    JOIN users u ON u.id = s.user_id
    JOIN branches b ON b.id = s.branch_id
    LEFT JOIN clients c ON c.id = s.client_id
    LEFT JOIN returns r ON r.sale_id = s.id
    LEFT JOIN users u_ret ON r.staff_id = u_ret.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) exit('Чек не найден в базе данных');

/* ================= ПОЛУЧАЕМ ТОВАРЫ + ПРОВЕРКА АКЦИЙ ================= */
$stmt = $pdo->prepare("
    SELECT si.*,
    (SELECT COUNT(*) FROM product_promotions pr 
     WHERE pr.product_name = si.product_name 
     AND DATE(?) BETWEEN pr.start_date AND pr.end_date) as was_promo
    FROM sale_items si 
    WHERE si.sale_id = ? 
    ORDER BY is_returned ASC, id ASC
");
$stmt->execute([$sale['created_at'], $saleId]);
$items = $stmt->fetchAll();

foreach ($items as &$it) {
    $priceWithDiscount = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
    $it['price_final'] = $priceWithDiscount;
    $it['row_total'] = $priceWithDiscount * $it['quantity'];
}
unset($it);
?>

<style>
    .sale-view-container { max-width: 600px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; }
    
    .receipt-paper { 
        background: #0b0b12; border: 1px solid #1f1f23; border-radius: 32px; 
        padding: 40px; position: relative; overflow: hidden;
    }
    
    /* Декоративная перфорация чека сверху */
    .receipt-paper::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 10px;
        background-image: radial-gradient(circle, #1a1a24 5px, transparent 6px);
        background-size: 20px 20px; background-position: -10px -10px;
    }

    .sale-header { text-align: center; margin-bottom: 30px; border-bottom: 1px dashed #333; padding-bottom: 20px; }
    
    .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
    .card-mini { background: rgba(255,255,255,0.02); padding: 15px; border-radius: 16px; border: 1px solid #1f1f23; }
    .card-mini label { display: block; font-size: 9px; text-transform: uppercase; color: #41414c; font-weight: 800; margin-bottom: 5px; }
    .card-mini span { font-size: 13px; font-weight: 600; }

    .items-list { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    .items-list th { text-align: left; font-size: 10px; color: #41414c; text-transform: uppercase; padding: 10px 0; }
    .items-list td { padding: 15px 0; border-top: 1px solid #16161a; }

    .tag-promo { background: rgba(255, 75, 43, 0.15); color: #ff4b2b; font-size: 9px; padding: 2px 6px; border-radius: 4px; font-weight: 900; margin-left: 5px; }
    .item-returned { opacity: 0.3; filter: grayscale(1); }
    
    .final-box { 
        background: linear-gradient(135deg, #785aff 0%, #4c2fff 100%);
        padding: 25px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 10px 30px rgba(120, 90, 255, 0.2);
    }
    .final-label { font-size: 13px; font-weight: 700; opacity: 0.8; text-transform: uppercase; }
    .final-sum { font-size: 28px; font-weight: 900; }

    .return-alert {
        margin-top: 25px; padding: 20px; border-radius: 18px;
        background: rgba(255, 75, 43, 0.05); border: 1px solid rgba(255, 75, 43, 0.2);
    }
</style>

<div class="sale-view-container">
    <div class="receipt-paper">
        <div class="sale-header">
            <h1 style="margin:0; font-size: 28px; font-weight: 900;">ЧЕК #<?= $sale['id'] ?></h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #41414c;"><?= date('d F Y, H:i', strtotime($sale['created_at'])) ?></p>
        </div>

        <div class="grid-info">
            <div class="card-mini">
                <label>Продавец</label>
                <span><?= h($sale['first_name'].' '.$sale['last_name']) ?></span>
            </div>
            <div class="card-mini">
                <label>Точка продажи</label>
                <span><?= h($sale['branch_name']) ?></span>
            </div>
            <div class="card-mini">
                <label>Покупатель</label>
                <span>
                    <?php if($sale['client_name']): ?>
                        <b style="color: #b866ff;"><?= h($sale['client_name']) ?></b>
                        <div style="font-size: 10px; opacity: 0.5;"><?= h($sale['client_phone']) ?></div>
                    <?php else: ?>
                        Розничный клиент
                    <?php endif; ?>
                </span>
            </div>
            <div class="card-mini">
                <label>Тип оплаты</label>
                <span><?= $sale['payment_type'] === 'card' ? '💳 Банковская карта' : '💵 Наличные' ?></span>
            </div>
        </div>

        <table class="items-list">
            <thead>
                <tr>
                    <th>Наименование товара</th>
                    <th style="text-align: center;">Кол-во</th>
                    <th style="text-align: right;">Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): 
                    $isRet = (int)$it['is_returned'] === 1;
                ?>
                <tr class="<?= $isRet ? 'item-returned' : '' ?>">
                    <td>
                        <div style="font-size: 14px; font-weight: 700;">
                            <?= h($it['product_name']) ?>
                            <?php if($it['was_promo'] > 0): ?><span class="tag-promo">АКЦИЯ</span><?php endif; ?>
                        </div>
                        <?php if($isRet): ?>
                            <div style="font-size: 10px; color: #ff4b2b; font-weight: 800;">ТОВАР ВОЗВРАЩЕН</div>
                        <?php elseif($it['discount'] > 0): ?>
                            <div style="font-size: 11px; color: #785aff; font-weight: 700;">Скидка <?= (float)$it['discount'] ?>%</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; opacity: 0.6;">x<?= (int)$it['quantity'] ?></td>
                    <td style="text-align: right; font-weight: 800; font-size: 15px;">
                        <?= number_format($it['row_total'], 0, '.', ' ') ?> L
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="final-box">
            <span class="final-label">Итого к оплате</span>
            <span class="final-sum"><?= number_format($sale['total_amount'], 0, '.', ' ') ?> MDL</span>
        </div>

        <?php if ($sale['return_1c_id']): ?>
        <div class="return-alert">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <span style="font-size: 20px;">⚠️</span>
                <b style="color: #ff4b2b; font-size: 12px; text-transform: uppercase;">Обнаружен возврат по чеку</b>
            </div>
            <div style="font-size: 13px; line-height: 1.5; opacity: 0.8;">
                Акт возврата в 1С: <b><?= h($sale['return_1c_id']) ?></b><br>
                Причина: <?= h($sale['defect_description'] ?: $sale['reason']) ?><br>
                Оформил: <?= h($sale['ret_f_name'].' '.$sale['ret_l_name']) ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php?page=sales_all" style="color: #41414c; text-decoration: none; font-size: 13px; font-weight: 700; transition: 0.2s;">
                ← Вернуться в журнал продаж
            </a>
        </div>
    </div>
</div>