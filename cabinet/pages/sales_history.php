<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// 1. ЛОГИКА ФИЛЬТРАЦИИ
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');

// 2. ЗАПРОС
$stmt = $pdo->prepare("
    SELECT 
        s.id, s.total_amount, s.payment_type, s.client_source, s.created_at, s.is_returned,
        c.name as client_name, c.phone as client_phone,
        (SELECT SUM(si.salary_amount) FROM sale_items si WHERE si.sale_id = s.id) as total_salary,
        (SELECT COUNT(*) FROM sale_items si 
         JOIN product_promotions pr ON pr.product_name = si.product_name 
         WHERE si.sale_id = s.id 
         AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date) as has_promo_items
    FROM sales s
    LEFT JOIN clients c ON s.client_id = c.id
    WHERE s.user_id = ?
      AND s.created_at BETWEEN ? AND ?
      AND s.total_amount > 0
    ORDER BY s.created_at DESC
");
$stmt->execute([$userId, $date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$sales = $stmt->fetchAll();

// 3. ГРУППИРОВКА
$grouped = [];
foreach ($sales as $s) {
    $day = date('d.m.Y', strtotime($s['created_at']));
    $grouped[$day]['items'][] = $s;
    if ($s['is_returned'] == 0) {
        $grouped[$day]['total'] = ($grouped[$day]['total'] ?? 0) + (float)$s['total_amount'];
        $grouped[$day]['total_salary'] = ($grouped[$day]['total_salary'] ?? 0) + (float)$s['total_salary'];
    }
}
?>

<style>
    .sh-container { font-family: 'Inter', sans-serif; max-width: 900px; margin: 0 auto; color: #fff; padding: 20px; }
    
    /* Шапка фильтра */
    .sh-filter-card { 
        background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 28px; padding: 25px; margin-bottom: 40px;
        display: flex; align-items: flex-end; gap: 20px; backdrop-filter: blur(10px);
    }
    .sh-f-group { display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .sh-f-group label { font-size: 11px; font-weight: 800; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 1px; }
    .sh-f-input { background: #000; border: 1px solid #333; padding: 14px; border-radius: 14px; color: #fff; outline: none; transition: 0.3s; }
    .sh-f-input:focus { border-color: #785aff; box-shadow: 0 0 15px rgba(120,90,255,0.2); }
    .sh-btn-apply { background: #785aff; color: #fff; border: none; padding: 14px 30px; border-radius: 14px; font-weight: 700; cursor: pointer; transition: 0.3s; }

    /* Группировка по дням */
    .sh-day-section { margin-bottom: 40px; }
    .sh-day-title { 
        display: flex; justify-content: space-between; align-items: center; 
        margin-bottom: 15px; padding: 0 10px;
    }
    .sh-date-text { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
    .sh-day-stats { text-align: right; line-height: 1.2; }
    .sh-day-total { font-size: 18px; font-weight: 900; color: #fff; }
    .sh-day-bonus { font-size: 13px; color: #7CFF6B; font-weight: 600; }

    /* Карточка продажи */
    .sh-sale-card {
        display: flex; justify-content: space-between; align-items: center;
        padding: 20px 25px; margin-bottom: 12px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 24px; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-decoration: none; color: inherit;
    }
    .sh-sale-card:hover { 
        transform: scale(1.02); background: rgba(120, 90, 255, 0.06); 
        border-color: rgba(120, 90, 255, 0.3); 
    }

    .sh-left { display: flex; align-items: center; gap: 20px; }
    .sh-icon { 
        width: 54px; height: 54px; background: rgba(255,255,255,0.03); 
        border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 24px;
    }
    
    .sh-main-info { display: flex; flex-direction: column; gap: 4px; }
    .sh-id-row { display: flex; align-items: center; gap: 10px; }
    .sh-id { font-size: 16px; font-weight: 800; }
    .sh-badge-promo { background: linear-gradient(135deg, #ff416c, #ff4b2b); color: #fff; font-size: 9px; padding: 3px 8px; border-radius: 8px; font-weight: 900; }
    .sh-badge-return { background: #ff4444; color: #fff; font-size: 9px; padding: 3px 8px; border-radius: 8px; font-weight: 900; }

    /* Клиент в одну строку */
    .sh-client-line { display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,0.5); }
    .sh-c-name { color: #b866ff; font-weight: 700; }
    .sh-c-phone { opacity: 0.6; font-family: monospace; }

    .sh-right { text-align: right; }
    .sh-amount { font-size: 19px; font-weight: 900; color: #fff; display: block; }
    .sh-bonus-val { font-size: 13px; color: #7CFF6B; font-weight: 700; }

    .sh-returned { opacity: 0.4; filter: grayscale(1); }
</style>

<div class="sh-container">
    
    <form class="sh-filter-card" method="GET">
        <input type="hidden" name="page" value="sales_history">
        <div class="sh-f-group">
            <label>Начало периода</label>
            <input type="date" name="from" class="sh-f-input" value="<?= h($date_from) ?>">
        </div>
        <div class="sh-f-group">
            <label>Конец периода</label>
            <input type="date" name="to" class="sh-f-input" value="<?= h($date_to) ?>">
        </div>
        <button class="sh-btn-apply">Показать</button>
    </form>

    <?php if (!$grouped): ?>
        <div style="text-align: center; padding: 100px 0; opacity: 0.2;">
            <div style="font-size: 80px; margin-bottom: 20px;">📂</div>
            <h2 style="font-weight: 800;">Продаж не найдено</h2>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $day => $data): ?>
            <div class="sh-day-section">
                <div class="sh-day-title">
                    <span class="sh-date-text"><?= $day === date('d.m.Y') ? '⭐ Сегодня' : h($day) ?></span>
                    <div class="sh-day-stats">
                        <div class="sh-day-total"><?= number_format($data['total'] ?? 0, 0, '.', ' ') ?> L</div>
                        <div class="sh-day-bonus">бонус <?= number_format($data['total_salary'] ?? 0, 2, '.', ' ') ?> L</div>
                    </div>
                </div>

                <?php foreach ($data['items'] as $r): 
                    $returned = ($r['is_returned'] == 1);
                ?>
                    <a href="?page=sale_view&id=<?= (int)$r['id'] ?>" class="sh-sale-card <?= $returned ? 'sh-returned' : '' ?>">
                        <div class="sh-left">
                            <div class="sh-icon">
                                <?php if($returned): ?> ↩️ <?php else: ?>
                                    <?= $r['payment_type'] === 'card' ? '💳' : '💵' ?>
                                <?php endif; ?>
                            </div>
                            <div class="sh-main-info">
                                <div class="sh-id-row">
                                    <span class="sh-id">Чек #<?= $r['id'] ?></span>
                                    <?php if($returned): ?><span class="sh-badge-return">ВОЗВРАТ</span><?php endif; ?>
                                    <?php if($r['has_promo_items'] > 0): ?><span class="sh-badge-promo">АКЦИЯ</span><?php endif; ?>
                                </div>
                                <div class="sh-client-line">
                                    <span style="opacity:0.8;"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                                    <span>•</span>
                                    <?php if($r['client_name']): ?>
                                        <span class="sh-c-name"><?= h($r['client_name']) ?></span>
                                        <span class="sh-c-phone"><?= h($r['client_phone']) ?></span>
                                    <?php else: ?>
                                        <span><?= h($r['client_source'] ?: 'Витрина') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="sh-right">
                            <span class="sh-amount"><?= $returned ? '-' : '' ?><?= number_format((float)$r['total_amount'], 0, '.', ' ') ?> L</span>
                            <?php if(!$returned): ?>
                                <span class="sh-bonus-val">+<?= number_format((float)$r['total_salary'], 2, '.', ' ') ?> L</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
