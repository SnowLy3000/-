<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);
$saleId = (int)($_GET['sale_id'] ?? $_GET['id'] ?? 0);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!$saleId) exit('ID не передан');

// 1. ОБНОВЛЕННЫЙ ЗАПРОС: Добавляем данные клиента
$stmt = $pdo->prepare("
    SELECT s.*, b.name AS branch_name, 
           r.id as return_db_id, r.return_1c_id, r.reason, r.defect_description, r.created_at as return_date,
           u_ret.first_name as return_staff_name,
           u_orig.first_name as seller_first_name, u_orig.last_name as seller_last_name,
           c.name as client_name, c.phone as client_phone
    FROM sales s
    LEFT JOIN branches b ON b.id = s.branch_id
    LEFT JOIN returns r ON r.sale_id = s.id
    LEFT JOIN users u_ret ON r.staff_id = u_ret.id
    LEFT JOIN users u_orig ON s.user_id = u_orig.id
    LEFT JOIN clients c ON s.client_id = c.id
    WHERE s.id = ? LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) exit('Чек не найден');

$is_returned = ($sale['is_returned'] == 1);

// 2. ТОВАРЫ: Проверяем участие в акциях на дату совершения продажи
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
$all_items = $stmt->fetchAll();

$photos = [];
if ($sale['return_db_id']) {
    $stmtP = $pdo->prepare("SELECT image_path FROM return_photos WHERE return_id = ?");
    $stmtP->execute([$sale['return_db_id']]);
    $photos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .invoice-view { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; color: #e8eefc; padding: 20px 20px 80px; }
    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
    
    .status-badge { padding: 8px 16px; border-radius: 12px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-ok { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }
    .status-partial { background: rgba(241, 196, 15, 0.15); color: #f1c40f; border: 1px solid rgba(241, 196, 15, 0.2); }
    .status-returned { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.2); }
    
    /* Обновленная сетка инфо: 4 колонки */
    .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 30px; }
    .info-item { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 18px; text-align: center; border: 1px solid rgba(255,255,255,0.05); }
    .info-item label { display: block; font-size: 9px; opacity: 0.4; text-transform: uppercase; margin-bottom: 6px; font-weight: 700; letter-spacing: 0.5px; }
    .info-item span { font-size: 13px; font-weight: 600; }

    .content-box { background: rgba(255,255,255,0.02); padding: 25px; border-radius: 28px; border: 1px solid rgba(255,255,255,0.05); }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .items-table th { text-align: left; font-size: 11px; opacity: 0.3; padding: 12px; text-transform: uppercase; }
    .items-table td { padding: 18px 12px; border-bottom: 1px solid rgba(255,255,255,0.03); }

    /* Бейджи для акций */
    .promo-tag { background: linear-gradient(135deg, #ff416c, #ff4b2b); color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 5px; font-weight: 800; margin-left: 6px; }

    .row-returned { opacity: 0.4; filter: grayscale(1); }
    .badge-returned-item { font-size: 9px; background: #e74c3c; color: #fff; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; font-weight: 800; }

    .summary-card {
        margin-top: 25px; padding: 25px; border-radius: 24px;
        background: linear-gradient(135deg, rgba(120,90,255,0.12) 0%, rgba(120,90,255,0.04) 100%);
        border: 1px solid rgba(120,90,255,0.15); display: flex; justify-content: space-between; align-items: center;
    }
</style>

<div class="invoice-view">
    <div class="invoice-header">
        <div>
            <h1 style="margin:0; font-size: 28px; letter-spacing: -1px;">Чек #<?= $saleId ?></h1>
            <div style="opacity:0.4; font-size:14px; margin-top:5px;">📅 <?= date('d.m.Y в H:i', strtotime($sale['created_at'])) ?></div>
        </div>
        <div class="status-badge <?= $is_returned ? 'status-returned' : ($sale['return_db_id'] ? 'status-partial' : 'status-ok') ?>">
            <?= $is_returned ? '🔙 Возврат' : ($sale['return_db_id'] ? '⚠️ Частичный' : '✅ Оплачено') ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-item"><label>📍 Точка</label><span><?= h($sale['branch_name']) ?></span></div>
        <div class="info-item"><label>💳 Метод</label><span><?= $sale['payment_type'] === 'card' ? 'Карта' : 'Наличные' ?></span></div>
        <div class="info-item"><label>👤 Кассир</label><span><?= h($sale['seller_first_name']) ?></span></div>
        <div class="info-item" style="border-color: rgba(184, 102, 255, 0.3);">
            <label style="color: #b866ff; opacity: 1;">👤 Клиент</label>
            <span style="font-size: 11px;"><?= $sale['client_name'] ? h($sale['client_name']) . '<br><small opacity="0.5">' . h($sale['client_phone']) . '</small>' : '—' ?></span>
        </div>
    </div>

    <div class="content-box">
        <h3 style="margin-top:0; font-size: 18px; font-weight: 800;">Список покупок</h3>
        <table class="items-table">
            <thead>
                <tr><th>Наименование</th><th style="text-align:right;">Цена</th><th style="text-align:center;">Кол-во</th><th style="text-align:right;">Доход</th></tr>
            </thead>
            <tbody>
                <?php 
                $totalSalary = 0;
                foreach ($all_items as $it): 
                    $isItemReturned = ($it['is_returned'] == 1);
                    $priceFinal = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
                    if (!$isItemReturned) {
                        $totalSalary += (float)$it['salary_amount'];
                    }
                ?>
                <tr class="<?= $isItemReturned ? 'row-returned' : '' ?>">
                    <td>
                        <div style="font-weight:600; font-size:14px;">
                            <?= h($it['product_name']) ?>
                            <?php if($it['was_promo'] > 0): ?><span class="promo-tag">АКЦИЯ</span><?php endif; ?>
                        </div>
                        <?php if($isItemReturned): ?>
                            <span class="badge-returned-item">ВОЗВРАЩЕНО</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; font-weight:700;"><?= number_format($priceFinal, 0, '.', ' ') ?> L</td>
                    <td style="text-align:center; opacity: 0.6;">×<?= (int)$it['quantity'] ?></td>
                    <td style="text-align:right; color:<?= $isItemReturned ? '#fff' : '#7CFF6B' ?>; font-weight:800;">
                        <?= $isItemReturned ? '0.00' : '+'.number_format($it['salary_amount'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-card">
            <div>
                <label style="font-size:10px; opacity:0.5; text-transform:uppercase; font-weight: 800;">Ваш бонус:</label>
                <div style="color:#7CFF6B; font-size:22px; font-weight:900;"><?= number_format($totalSalary, 2) ?> L</div>
            </div>
            <div style="text-align:right;">
                <label style="font-size:10px; opacity:0.5; text-transform:uppercase; font-weight: 800;">Итого к оплате:</label>
                <div style="font-size:32px; font-weight:900; letter-spacing:-1px;"><?= number_format($sale['total_amount'], 0, '.', ' ') ?> L</div>
            </div>
        </div>
    </div>

    <?php if ($sale['return_db_id']): ?>
        <div style="margin-top: 30px; background: rgba(231, 76, 60, 0.03); border: 1px solid rgba(231, 76, 60, 0.1); border-radius: 28px; padding: 25px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                <div style="width:40px; height:40px; background:rgba(231,76,60,0.1); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#e74c3c; font-size:20px;">🔙</div>
                <div>
                    <h4 style="margin:0; font-size:16px;">Детали возврата</h4>
                    <div style="font-size:12px; opacity:0.5;"><?= date('d.m.Y H:i', strtotime($sale['return_date'])) ?></div>
                </div>
            </div>
            <div style="font-size: 14px; line-height: 1.6; color: rgba(255,255,255,0.7);">
                <?= nl2br(h($sale['defect_description'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-top: 40px; display: flex; gap: 20px; justify-content: center; align-items: center;">
        <a href="index.php?page=sales_history" style="opacity:0.4; text-decoration:none; font-size:13px; font-weight:700; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.4">← ВЕРНУТЬСЯ В ИСТОРИЮ</a>
    </div>
</div>
