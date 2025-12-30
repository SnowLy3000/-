<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);
$saleId = (int)($_GET['sale_id'] ?? 0);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!$saleId) exit('Некорректный чек');

// Запрос чека с информацией о филиале
$stmt = $pdo->prepare("
    SELECT s.*, b.name AS branch_name
    FROM sales s
    LEFT JOIN branches b ON b.id = s.branch_id
    WHERE s.id = ? AND s.user_id = ?
    LIMIT 1
");
$stmt->execute([$saleId, $userId]);
$sale = $stmt->fetch();

if (!$sale) exit('Чек не найден');

// Запрос товаров (теперь берем зафиксированный salary_amount)
$stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();
?>

<style>
    .invoice-view { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; color: #eee; }
    
    .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
    }

    .status-badge {
        background: rgba(124, 255, 107, 0.1);
        color: #7CFF6B;
        padding: 6px 14px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        border: 1px solid rgba(124, 255, 107, 0.2);
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 15px;
        background: rgba(255,255,255,0.03);
        padding: 20px;
        border-radius: 20px;
        margin-bottom: 25px;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .info-item label {
        display: block;
        font-size: 10px;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        margin-bottom: 6px;
        font-weight: 700;
    }

    .info-item span { font-size: 14px; font-weight: 600; color: #fff; }

    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th {
        text-align: left;
        font-size: 11px;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        padding: 12px 10px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .items-table td { padding: 16px 10px; border-bottom: 1px solid rgba(255,255,255,0.03); }

    .brand-tag { font-size: 10px; background: rgba(120,90,255,0.1); padding: 3px 8px; border-radius: 6px; color: #785aff; font-weight: 700; }
    .discount-tag { background: rgba(255, 193, 7, 0.15); color: #ffc107; padding: 3px 7px; border-radius: 6px; font-size: 10px; font-weight: 800; }

    .salary-val { color: #7CFF6B; font-weight: 700; font-size: 13px; }

    .summary-card {
        margin-top: 30px;
        padding: 25px;
        background: linear-gradient(145deg, rgba(120,90,255,0.1) 0%, rgba(120,90,255,0.02) 100%);
        border-radius: 24px;
        border: 1px solid rgba(120,90,255,0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .total-amount { color: #fff; font-size: 28px; font-weight: 800; }
    .total-salary { color: #7CFF6B; font-size: 14px; font-weight: 700; margin-top: 5px; }

    @media (max-width: 600px) {
        .items-table th:nth-child(2), .items-table td:nth-child(2) { display: none; }
        .invoice-header { flex-direction: column; gap: 15px; }
    }
</style>

<div class="invoice-view">
    
    <div class="invoice-header">
        <div>
            <h2 style="margin:0; font-size: 26px;">Чек #<?= $saleId ?></h2>
            <div style="color:rgba(255,255,255,0.4); font-size:14px; margin-top:6px;">
                📅 <?= date('d.m.Y', strtotime($sale['created_at'])) ?> в <?= date('H:i', strtotime($sale['created_at'])) ?>
            </div>
        </div>
        <div class="status-badge">✅ Завершен</div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <label>📍 Точка</label>
            <span><?= h($sale['branch_name'] ?? 'Склад') ?></span>
        </div>
        <div class="info-item">
            <label>💳 Оплата</label>
            <span><?= $sale['payment_type'] === 'card' ? 'Карта' : 'Наличные' ?></span>
        </div>
        <div class="info-item">
            <label>📢 Источник</label>
            <span><?= h($sale['client_source'] ?: 'Визит') ?></span>
        </div>
    </div>

    <div class="card" style="border-radius: 24px; padding: 20px;">
        <h3 style="font-size:18px; margin-bottom:20px; font-weight:700;">Детализация</h3>
        
        <?php if (!$items): ?>
            <div style="text-align:center; padding: 40px; opacity:0.3;">Товары отсутствуют</div>
        <?php else: ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Бренд</th>
                        <th style="text-align:right;">Цена</th>
                        <th style="text-align:center;">Кол-во</th>
                        <th style="text-align:right;">ЗП</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalSalary = 0;
                    foreach ($items as $it): 
                        $priceFinal = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
                        $rowSum = $priceFinal * $it['quantity'];
                        $totalSalary += (float)$it['salary_amount'];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= h($it['product_name']) ?></div>
                            <?php if($it['discount'] > 0): ?>
                                <span class="discount-tag">−<?= (float)$it['discount'] ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="brand-tag"><?= h($it['brand'] ?: 'OEM') ?></span></td>
                        <td style="text-align:right;">
                            <div style="font-weight:700;"><?= number_format($priceFinal, 0, '.', ' ') ?> L</div>
                        </td>
                        <td style="text-align:center;">×<?= (int)$it['quantity'] ?></td>
                        <td style="text-align:right;">
                            <span class="salary-val">+<?= number_format($it['salary_amount'], 2) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary-card">
                <div>
                    <div style="color: rgba(255,255,255,0.4); font-size: 13px; font-weight: 600;">Ваш чистый доход:</div>
                    <div class="total-salary">+<?= number_format($totalSalary, 2, '.', ' ') ?> L</div>
                </div>
                <div style="text-align: right;">
                    <div style="color: rgba(255,255,255,0.4); font-size: 13px; font-weight: 600;">Сумма чека:</div>
                    <div class="total-amount"><?= number_format($sale['total_amount'], 0, '.', ' ') ?> L</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        <p>Данные зафиксированы в системе и не подлежат ручному изменению.</p>
        <a href="/cabinet/index.php?page=sales_history" style="color:#785aff; text-decoration:none; font-weight:700; display: inline-flex; align-items: center; gap: 8px;">
            <span>←</span> Вернуться к истории
        </a>
    </div>

</div>
