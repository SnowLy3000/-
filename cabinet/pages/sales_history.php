<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Получение списка продаж с суммой ЗП из сохраненных данных
$stmt = $pdo->prepare("
    SELECT 
        s.id, 
        s.total_amount, 
        s.payment_type, 
        s.client_source, 
        s.created_at,
        (SELECT SUM(si.salary_amount) FROM sale_items si WHERE si.sale_id = s.id) as total_salary
    FROM sales s
    WHERE s.user_id = ?
      AND s.total_amount > 0
    ORDER BY s.created_at DESC
    LIMIT 300
");
$stmt->execute([$userId]);
$sales = $stmt->fetchAll();

// Группировка по дням
$grouped = [];
foreach ($sales as $s) {
    $day = date('d.m.Y', strtotime($s['created_at']));
    $grouped[$day]['items'][] = $s;
    $grouped[$day]['total'] = ($grouped[$day]['total'] ?? 0) + (float)$s['total_amount'];
    $grouped[$day]['total_salary'] = ($grouped[$day]['total_salary'] ?? 0) + (float)$s['total_salary'];
}
?>

<style>
    .sales-history { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; color: #fff; }
    .history-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 25px; margin-bottom: 25px; }
    
    .day-group { margin-bottom: 30px; }
    .day-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        padding: 0 10px 15px 10px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        margin-bottom: 15px;
    }
    .day-date { font-size: 16px; font-weight: 800; color: rgba(255,255,255,0.9); }
    .day-stats { text-align: right; }
    .day-sum { font-size: 14px; color: #fff; font-weight: 600; display: block; }
    .day-salary { font-size: 12px; color: #7CFF6B; font-weight: 800; background: rgba(124,255,107,0.1); padding: 2px 8px; border-radius: 8px; }

    .sale-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        margin-bottom: 10px;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 20px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
    }
    .sale-item:hover { 
        background: rgba(120,90,255,0.05); 
        border-color: rgba(120,90,255,0.3);
        transform: scale(1.02);
    }

    .sale-info { display: flex; align-items: center; gap: 15px; }
    .sale-icon { 
        width: 44px; height: 44px; 
        background: rgba(255,255,255,0.05); 
        border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 20px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
    }
    .sale-time { font-size: 15px; font-weight: 700; color: #fff; }
    .sale-meta { font-size: 12px; color: rgba(255,255,255,0.4); margin-top: 3px; }

    .sale-values { text-align: right; }
    .val-amount { font-size: 16px; font-weight: 800; color: #fff; display: block; }
    .val-salary { font-size: 13px; color: #7CFF6B; font-weight: 700; margin-top: 2px; display: inline-block; }
</style>

<div class="sales-history">
    <div class="history-card">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 30px;">📊</div>
            <div>
                <h2 style="margin:0;">Мои продажи</h2>
                <p style="margin:5px 0 0 0; font-size:13px; color: rgba(255,255,255,0.4);">История ваших чеков и начисленного заработка</p>
            </div>
        </div>
    </div>

    <?php if (!$grouped): ?>
        <div class="history-card" style="text-align: center; padding: 60px; opacity: 0.3;">
            <div style="font-size: 50px; margin-bottom: 10px;">📉</div>
            <p>Продаж пока нет. Самое время что-нибудь продать!</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $day => $data): ?>
            <div class="day-group">
                <div class="day-header">
                    <span class="day-date">
                        <?= $day === date('d.m.Y') ? '⭐ Сегодня' : h($day) ?>
                    </span>
                    <div class="day-stats">
                        <span class="day-sum"><?= number_format($data['total'], 0, '.', ' ') ?> L</span>
                        <span class="day-salary">+<?= number_format($data['total_salary'], 2, '.', ' ') ?> L</span>
                    </div>
                </div>

                <?php foreach ($data['items'] as $r): ?>
                    <a href="/cabinet/index.php?page=sale_view&sale_id=<?= (int)$r['id'] ?>" class="sale-item">
                        <div class="sale-info">
                            <div class="sale-icon">
                                <?= $r['payment_type'] === 'card' ? '💳' : '💵' ?>
                            </div>
                            <div class="sale-details">
                                <span class="sale-time">Чек #<?= $r['id'] ?> · <?= date('H:i', strtotime($r['created_at'])) ?></span>
                                <div class="sale-meta"><?= h($r['client_source'] ?: 'Прямой визит') ?></div>
                            </div>
                        </div>
                        <div class="sale-values">
                            <span class="val-amount"><?= number_format((float)$r['total_amount'], 0, '.', ' ') ?> L</span>
                            <span class="val-salary">+<?= number_format((float)$r['total_salary'], 2, '.', ' ') ?> L</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
