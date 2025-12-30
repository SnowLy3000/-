<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ: доступ только для тех, у кого есть право смотреть журнал цен
require_role('view_price_log');

// Получаем список всех переоценок с подсчетом количества товаров внутри
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
    .log-table { width: 100%; border-collapse: collapse; }
    .log-table th { text-align: left; padding: 15px; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); letter-spacing: 1px; }
    .log-table td { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    .log-table tr:hover { background: rgba(120, 90, 255, 0.02); }
    
    .id-badge { background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 6px; font-family: monospace; color: rgba(255,255,255,0.5); }
    .items-badge { background: rgba(120, 90, 255, 0.1); color: #b866ff; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 12px; }
    
    .progress-container { width: 120px; }
    .progress-bar-bg { width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-top: 6px; overflow: hidden; }
    .progress-bar-fill { height: 100%; transition: width 0.5s ease; }

    .btn-detail { 
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
        color: #fff; padding: 8px 16px; border-radius: 10px; text-decoration: none; 
        font-size: 12px; font-weight: 600; transition: 0.3s; 
    }
    .btn-detail:hover { background: #785aff; border-color: #785aff; box-shadow: 0 4px 12px rgba(120,90,255,0.3); }
</style>

<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
    <div>
        <h1 style="margin:0; font-size: 26px;">📜 Журнал переоценок</h1>
        <p class="muted" style="margin:5px 0 0 0;">История изменения прайса и контроль уведомления персонала</p>
    </div>
    <a href="?page=price_revaluation" class="btn" style="background: #785aff; padding: 12px 20px; border-radius: 12px; font-weight: 700;">➕ Новая переоценка</a>
</div>

<div class="card" style="padding:0; overflow:hidden; border-radius: 20px;">
    <?php if (!$logs): ?>
        <div style="text-align: center; padding: 80px 20px; opacity: 0.3;">
            <div style="font-size: 40px; margin-bottom: 10px;">📂</div>
            <div>История переоценок пока пуста</div>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Дата и Время</th>
                        <th>Администратор</th>
                        <th>Позиций</th>
                        <th>Ознакомление</th>
                        <th style="text-align: right;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Кэшируем общее кол-во активных юзеров для расчета %
                    $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
                    if($total_users === 0) $total_users = 1;

                    foreach ($logs as $l): 
                        // Считаем сколько человек подтвердило конкретно этот акт
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM price_revaluation_confirmations WHERE revaluation_id = ?");
                        $stmt->execute([$l['id']]);
                        $confirms = (int)$stmt->fetchColumn();
                        $percent = min(100, round(($confirms / $total_users) * 100));
                        $barColor = $percent >= 100 ? '#7CFF6B' : ($percent > 50 ? '#ffbb33' : '#785aff');
                    ?>
                    <tr>
                        <td><span class="id-badge">#<?= $l['id'] ?></span></td>
                        <td>
                            <div style="font-weight: 700;"><?= date('d.m.Y', strtotime($l['created_at'])) ?></div>
                            <div class="muted" style="font-size: 12px;"><?= date('H:i', strtotime($l['created_at'])) ?></div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: rgba(120,90,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 10px; color: #b866ff;">
                                    <?= mb_substr($l['first_name'], 0, 1) ?>
                                </div>
                                <?= h($l['last_name'] . ' ' . $l['first_name']) ?>
                            </div>
                        </td>
                        <td><span class="items-badge"><?= $l['items_count'] ?> тов.</span></td>
                        <td>
                            <div class="progress-container">
                                <div style="font-size: 11px; display: flex; justify-content: space-between;">
                                    <span><?= $confirms ?> / <?= $total_users ?></span>
                                    <span style="color: <?= $barColor ?>; font-weight: 800;"><?= $percent ?>%</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?= $percent ?>%; background: <?= $barColor ?>; box-shadow: 0 0 10px <?= $barColor ?>44;"></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <a href="?page=price_confirm&id=<?= $l['id'] ?>" class="btn-detail">Открыть акт</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
