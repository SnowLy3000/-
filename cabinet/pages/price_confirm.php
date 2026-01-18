<?php
// 1. Безопасность и общие функции
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

// Проверяем авторизацию
$user_id = $_SESSION['user']['id'] ?? 0; // В твоем admin-файле было $_SESSION['user']['id']
if (!$user_id) exit('Access Denied');

// Получаем текущую должность сотрудника для фильтрации актов
$stmtPos = $pdo->prepare("SELECT position_id FROM user_positions WHERE user_id = ? LIMIT 1");
$stmtPos->execute([$user_id]);
$u_pos_id = (int)$stmtPos->fetchColumn();

// 2. Логика получения ID акта
$reval_id = (int)($_GET['id'] ?? 0);

// Если ID не указан, находим подходящий по должности непрочитанный акт за 24 часа
if (!$reval_id) {
    $stmt = $pdo->query("
        SELECT id, target_positions FROM price_revaluations 
        WHERE created_at > NOW() - INTERVAL 1 DAY
        ORDER BY id ASC
    ");
    $recent_acts = $stmt->fetchAll();

    foreach ($recent_acts as $act) {
        // Защита от PHP 8.1 (null to string)
        $targets = json_decode((string)$act['target_positions'], true);
        
        // Видим акт, если он для всех (empty) ИЛИ если наша должность в списке
        $is_for_me = empty($targets) || in_array($u_pos_id, $targets);
        
        if ($is_for_me) {
            $check = $pdo->prepare("SELECT id FROM price_revaluation_confirmations WHERE revaluation_id = ? AND user_id = ?");
            $check->execute([$act['id'], $user_id]);
            if (!$check->fetch()) {
                $reval_id = (int)$act['id'];
                break; 
            }
        }
    }
}

// Если подтверждать нечего
if (!$reval_id) {
    echo '<div class="card" style="text-align:center; padding:80px 20px; border-radius: 25px;">
            <div style="font-size:60px; margin-bottom: 20px;">✅</div>
            <h2 style="color: #7CFF6B;">Все цены актуальны</h2>
            <p class="muted">Для вашей должности новых переоценок не найдено.</p>
            <a href="?page=dashboard" class="btn" style="display:inline-block; margin-top:25px; background: #785aff; padding: 12px 25px; border-radius: 12px; text-decoration: none; color: #fff;">Вернуться на главную</a>
          </div>';
    return;
}

// 3. Получаем товары для этого акта
$stmt = $pdo->prepare("
    SELECT ri.*, p.name 
    FROM price_revaluation_items ri 
    JOIN products p ON p.id = ri.product_id 
    WHERE ri.revaluation_id = ?
");
$stmt->execute([$reval_id]);
$rows = $stmt->fetchAll();

// 4. Обработка подтверждения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_all'])) {
    $on_shift = isset($_SESSION['shift_id']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO price_revaluation_confirmations (revaluation_id, user_id, is_on_shift, confirmed_at, created_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$reval_id, $user_id, $on_shift]);
    
    echo "<script>alert('Ознакомление подтверждено!'); location.href='?page=dashboard';</script>";
    exit;
}
?>
<style>
    .confirm-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    .confirm-table th { text-align: left; padding: 12px; color: rgba(255,255,255,0.4); font-size: 12px; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .confirm-table td { padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .price-old { color: rgba(255,255,255,0.3); text-decoration: line-through; font-size: 13px; }
    .price-new { color: #7CFF6B; font-weight: 800; font-size: 16px; }
    .check-box { width: 22px; height: 22px; cursor: pointer; accent-color: #785aff; }
    
    .alert-shift {
        background: rgba(120, 90, 255, 0.05);
        border: 1px solid rgba(120, 90, 255, 0.2);
        padding: 20px;
        border-radius: 15px;
        margin-top: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
</style>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="margin:0;">📝 Переоценка №<?= $reval_id ?></h2>
            <p class="muted" style="margin:5px 0 0 0;">Подтвердите ознакомление с новыми ценами</p>
        </div>
        <button onclick="window.print()" class="btn" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; cursor: pointer;">🖨️ Печать акта</button>
    </div>

    <form method="POST">
        <table class="confirm-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Проверил</th>
                    <th>Товар</th>
                    <th>Было</th>
                    <th>Стало</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><input type="checkbox" required class="check-box"></td>
                    <td><b style="font-size: 15px;"><?= h($r['name']) ?></b></td>
                    <td><span class="price-old"><?= number_format($r['old_price'], 2) ?> L</span></td>
                    <td><span class="price-new"><?= number_format($r['new_price'], 2) ?> L</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="alert-shift">
            <input type="checkbox" required id="final_check" class="check-box" style="width: 30px; height: 30px;">
            <label for="final_check" style="cursor: pointer;">
                <b style="color: #785aff; display: block; margin-bottom: 4px;">ПОДТВЕРЖДЕНИЕ ОЗНАКОМЛЕНИЯ</b>
                <span class="muted" style="font-size: 13px;">
                    Я подтверждаю, что изучил новые цены. 
                    <?php if (isset($_SESSION['shift_id'])): ?>
                        Я нахожусь на смене и обязуюсь заменить физические ценники.
                    <?php endif; ?>
                </span>
            </label>
        </div>

        <button type="submit" name="confirm_all" class="btn" style="width: 100%; height: 55px; margin-top: 25px; background: #2ecc71; color: #fff; border: none; border-radius: 15px; font-size: 16px; font-weight: 800; cursor: pointer;">
            🚀 ПОДТВЕРДИТЬ И ОБНОВИТЬ
        </button>
    </form>
</div>
