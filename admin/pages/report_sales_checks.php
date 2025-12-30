<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ: доступ по праву просмотра отчетов
require_role('view_reports');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_GET['user_id'] ?? 0);
$branchId = (int)($_GET['branch_id'] ?? 0);

$sql = "
SELECT
    s.id,
    s.created_at,
    s.payment_type,
    u.first_name, u.last_name,
    b.name AS branch_name,
    SUM(
        CEIL(si.price - (si.price * si.discount / 100)) * si.quantity
    ) AS total
FROM sales s
JOIN users u ON u.id = s.user_id
JOIN branches b ON b.id = s.branch_id
JOIN sale_items si ON si.sale_id = s.id
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

$sql .= "
GROUP BY s.id
ORDER BY s.created_at DESC
LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<style>
    .report-table { width: 100%; border-collapse: collapse; }
    .report-table th { text-align: left; padding: 12px 15px; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.1); }
    .report-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
    .report-table tr:hover { background: rgba(120, 90, 255, 0.03); }
    
    .pay-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .pay-card { background: rgba(0, 153, 255, 0.1); color: #0099ff; }
    .pay-cash { background: rgba(255, 187, 51, 0.1); color: #ffbb33; }
    
    .btn-mini { padding: 6px 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; text-decoration: none; font-size: 12px; transition: 0.2s; }
    .btn-mini:hover { background: #785aff; border-color: #785aff; }
</style>

<div style="margin-bottom: 25px;">
    <h1 style="margin:0; font-size: 22px;">🧾 Детализация чеков</h1>
    <p class="muted" style="margin:5px 0 0 0;">Список последних операций по выбранным фильтрам</p>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
    <div style="overflow-x: auto;">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Дата и время</th>
                    <th>Сотрудник</th>
                    <th>Филиал</th>
                    <th>Оплата</th>
                    <th>Сумма</th>
                    <th style="text-align: right;">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; opacity: 0.3;">Чеков не найдено</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <span style="font-weight: 600;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
                            <span class="muted" style="font-size: 12px; margin-left: 5px;"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                        </td>
                        <td><b><?= h($r['last_name']) ?></b> <?= mb_substr($r['first_name'],0,1) ?>.</td>
                        <td><span class="muted"><?= h($r['branch_name']) ?></span></td>
                        <td>
                            <?php if ($r['payment_type'] === 'card'): ?>
                                <span class="pay-badge pay-card">💳 КАРТА</span>
                            <?php else: ?>
                                <span class="pay-badge pay-cash">💵 НАЛ</span>
                            <?php endif; ?>
                        </td>
                        <td><b style="font-size: 15px;"><?= number_format($r['total'], 2) ?> L</b></td>
                        <td style="text-align: right;">
                            <a class="btn-mini" href="/admin/index.php?page=sale_view&sale_id=<?= $r['id'] ?>">Просмотр</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
