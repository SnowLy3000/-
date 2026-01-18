<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('price_log');

// Получаем список всех переоценок
$stmt = $pdo->query("
    SELECT 
        r.*, 
        u.first_name, u.last_name,
        (SELECT COUNT(*) FROM price_revaluation_items WHERE revaluation_id = r.id) as items_count
    FROM price_revaluations r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");
$logs = $stmt->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .pl-container { font-family: 'Inter', sans-serif; color: #fff; }
    
    .pl-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.01); border-radius: 20px; overflow: hidden; }
    .pl-table th { padding: 12px 15px; text-align: left; font-size: 9px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.08); }
    .pl-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    .id-tag { font-family: monospace; opacity: 0.4; font-size: 11px; }
    .items-count { background: rgba(120, 90, 255, 0.1); color: #b866ff; padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; }

    /* Прогресс бар */
    .prog-bg { width: 100%; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; margin-top: 5px; overflow: hidden; }
    .prog-fill { height: 100%; transition: 0.3s; }

    /* Детали (Аккордеон) */
    .details-row { display: none; background: rgba(0,0,0,0.15); }
    .details-box { padding: 20px; border-left: 3px solid #785aff; margin: 10px; background: rgba(255,255,255,0.01); border-radius: 0 15px 15px 0; }
    
    .inner-t { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .inner-t th { font-size: 8px; color: rgba(255,255,255,0.2); border: none; padding: 5px 10px; }
    .inner-t td { padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 12px; }

    .p-old { color: rgba(255,255,255,0.2); text-decoration: line-through; }
    .p-new { color: #7CFF6B; font-weight: 800; }
    .debtor-chip { font-size: 10px; padding: 3px 8px; background: rgba(255,75,43,0.1); color: #ff4b2b; border: 1px solid rgba(255,75,43,0.15); border-radius: 6px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
    <div>
        <h1 style="margin:0; font-size: 22px; font-weight: 900;">📜 Журнал переоценок</h1>
        <p style="margin:5px 0 0 0; font-size: 13px; opacity: 0.4;">История изменения цен и дисциплина персонала</p>
    </div>
    <a href="?page=price_revaluation" class="btn" style="background: #785aff; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 13px;">➕ Создать акт</a>
</div>

<div class="card" style="padding:0; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
    <table class="pl-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Дата / Время</th>
                <th>Администратор</th>
                <th>Позиций</th>
                <th>Ознакомление</th>
                <th style="text-align: right;">Детали</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?: 1;

            foreach ($logs as $l): 
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM price_revaluation_confirmations WHERE revaluation_id = ?");
                $stmt->execute([$l['id']]);
                $confirms = (int)$stmt->fetchColumn();
                
                $percent = min(100, round(($confirms / $total_users) * 100));
                $color = $percent >= 100 ? '#7CFF6B' : ($percent > 50 ? '#ffbb33' : '#785aff');

                // Товары
                $stmtItems = $pdo->prepare("SELECT ri.*, p.name FROM price_revaluation_items ri JOIN products p ON p.id = ri.product_id WHERE ri.revaluation_id = ?");
                $stmtItems->execute([$l['id']]);
                $items = $stmtItems->fetchAll();

                // Должники
                $stmtDebtors = $pdo->prepare("
                    SELECT first_name, last_name FROM users 
                    WHERE status = 'active' 
                    AND id NOT IN (SELECT user_id FROM price_revaluation_confirmations WHERE revaluation_id = ?)
                ");
                $stmtDebtors->execute([$l['id']]);
                $debtors = $stmtDebtors->fetchAll();
            ?>
            <tr onclick="toggleDetails(<?= $l['id'] ?>)" style="cursor:pointer;">
                <td><span class="id-tag">#<?= $l['id'] ?></span></td>
                <td>
                    <b><?= date('d.m.y', strtotime($l['created_at'])) ?></b>
                    <span style="opacity:0.3; font-size:11px; margin-left:5px;"><?= date('H:i', strtotime($l['created_at'])) ?></span>
                </td>
                <td><span style="opacity: 0.8;"><?= h($l['first_name'] . ' ' . $l['last_name']) ?></span></td>
                <td><span class="items-count"><?= $l['items_count'] ?></span></td>
                <td>
                    <div style="font-size: 10px; display: flex; justify-content: space-between; width: 100px; font-weight: 700;">
                        <span><?= $confirms ?>/<?= $total_users ?></span>
                        <span style="color:<?= $color ?>;"><?= $percent ?>%</span>
                    </div>
                    <div class="prog-bg">
                        <div class="prog-fill" style="width:<?= $percent ?>%; background:<?= $color ?>;"></div>
                    </div>
                </td>
                <td style="text-align: right;">
                    <button class="btn" style="background: rgba(255,255,255,0.05); font-size: 11px; padding: 5px 12px; border: 1px solid #333;">ИНФО</button>
                </td>
            </tr>

            <tr id="details-<?= $l['id'] ?>" class="details-row">
                <td colspan="6">
                    <div class="details-box">
                        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                            <div>
                                <h4 style="margin: 0; font-size: 12px; color: #785aff; text-transform: uppercase;">📦 Изменения в акте:</h4>
                                <table class="inner-t">
                                    <thead>
                                        <tr>
                                            <th>Продукт</th>
                                            <th>Старая</th>
                                            <th>Новая</th>
                                            <th>Разница</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $it): 
                                            $diff = $it['new_price'] - $it['old_price'];
                                        ?>
                                        <tr>
                                            <td><b><?= h($it['name']) ?></b></td>
                                            <td><span class="p-old"><?= number_format($it['old_price'], 0) ?></span></td>
                                            <td><span class="p-new"><?= number_format($it['new_price'], 0) ?></span></td>
                                            <td style="color: <?= $diff > 0 ? '#ff4b2b' : '#7CFF6B' ?>; font-weight: 900;">
                                                <?= ($diff > 0 ? '↑ ' : '↓ ') . abs(number_format($diff, 0)) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 12px 0; font-size: 12px; color: #ffbb33; text-transform: uppercase;">⏳ Ожидают подтверждения:</h4>
                                <?php if (empty($debtors)): ?>
                                    <div style="color: #7CFF6B; font-size: 13px; font-weight: 800; padding: 10px; background: rgba(124, 255, 107, 0.05); border-radius: 10px; text-align: center;">✅ Все ознакомлены</div>
                                <?php else: ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                        <?php foreach ($debtors as $d): ?>
                                            <div class="debtor-chip">👤 <?= h($d['last_name']) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    const isVisible = row.style.display === 'table-row';
    document.querySelectorAll('.details-row').forEach(r => r.style.display = 'none');
    row.style.display = isVisible ? 'none' : 'table-row';
}
</script>