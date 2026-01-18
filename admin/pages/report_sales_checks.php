<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_GET['user_id'] ?? 0);
$branchId = (int)($_GET['branch_id'] ?? 0);

// ОБНОВЛЕННЫЙ SQL: Добавили клиента и проверку акций
$sql = "
SELECT
    s.id,
    s.created_at,
    s.payment_type,
    u.first_name, u.last_name,
    b.name AS branch_name,
    c.name as client_name,
    SUM(CEIL(si.price - (si.price * si.discount / 100)) * si.quantity) AS total,
    -- Проверка наличия акционных товаров в чеке
    (SELECT COUNT(*) FROM sale_items sit 
     JOIN product_promotions pr ON pr.product_name = sit.product_name 
     WHERE sit.sale_id = s.id 
     AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date) as promo_count
FROM sales s
JOIN users u ON u.id = s.user_id
JOIN branches b ON b.id = s.branch_id
JOIN sale_items si ON si.sale_id = s.id
LEFT JOIN clients c ON c.id = s.client_id
WHERE s.total_amount > 0
";

$params = [];
if ($userId) {
    $sql .= " AND s.user_id = ?";
    $params[] = $userId;
}
if ($branchId) {
    $sql .= " AND s.branch_id = ?";
    $params[] = $branchId;
}

$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<style>
    .compact-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.01); }
    .compact-table th { 
        text-align: left; padding: 10px 15px; font-size: 9px; 
        text-transform: uppercase; color: rgba(255,255,255,0.3); 
        border-bottom: 1px solid rgba(255,255,255,0.08); letter-spacing: 0.5px;
    }
    .compact-table td { padding: 10px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }
    .compact-table tr:hover { background: rgba(120, 90, 255, 0.03); }
    
    .pay-mini { font-size: 10px; font-weight: 700; opacity: 0.8; }
    .promo-dot { 
        width: 7px; height: 7px; background: #ff4b2b; 
        border-radius: 50%; display: inline-block; 
        margin-right: 5px; box-shadow: 0 0 8px #ff4b2b; 
    }
    .client-label { display: block; font-size: 11px; color: #b866ff; font-weight: 600; margin-top: 2px; }
    .btn-circle { 
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; background: rgba(255,255,255,0.05); 
        border-radius: 8px; color: #fff; text-decoration: none; transition: 0.2s;
    }
    .btn-circle:hover { background: #785aff; }
</style>

<div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
    <div style="font-size: 20px;">🧾</div>
    <div>
        <h1 style="margin:0; font-size: 18px; font-weight: 800;">Детализация чеков</h1>
        <p style="margin:0; font-size: 12px; opacity: 0.4;">Последние 500 операций</p>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05);">
    <div style="overflow-x: auto;">
        <table class="compact-table">
            <thead>
                <tr>
                    <th>Дата / Время</th>
                    <th>Сотрудник / Клиент</th>
                    <th>Филиал</th>
                    <th>Оплата</th>
                    <th>Сумма</th>
                    <th style="text-align: right;">Инфо</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; opacity: 0.3;">Чеков не найдено</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <span style="font-weight: 600;"><?= date('d.m.y', strtotime($r['created_at'])) ?></span>
                            <span style="font-size: 11px; opacity: 0.4; margin-left: 4px;"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                        </td>
                        <td>
                            <b><?= h($r['last_name']) ?></b>
                            <?php if($r['client_name']): ?>
                                <span class="client-label">👤 <?= h($r['client_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span style="opacity: 0.6;"><?= h($r['branch_name']) ?></span></td>
                        <td>
                            <span class="pay-mini">
                                <?= $r['payment_type'] === 'card' ? '💳 КАРТА' : '💵 НАЛ' ?>
                            </span>
                        </td>
                        <td>
                            <?php if($r['promo_count'] > 0): ?><span class="promo-dot" title="Акционный товар"></span><?php endif; ?>
                            <b style="font-size: 14px;"><?= number_format($r['total'], 0) ?> L</b>
                        </td>
                        <td style="text-align: right;">
                            <a class="btn-circle" href="/admin/index.php?page=sale_view&sale_id=<?= $r['id'] ?>">🔍</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>