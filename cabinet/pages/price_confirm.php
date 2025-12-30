<?php
// 1. Безопасность и общие функции
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// Определяем функцию h, если она не была определена глобально
if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

// Проверяем авторизацию (предположим, данные юзера в сессии)
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) exit('Access Denied');

// 2. Логика получения ID акта
$reval_id = (int)($_GET['id'] ?? 0);

// Если ID не указан, находим последний акт, который этот юзер еще НЕ подтвердил
if (!$reval_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM price_revaluations 
        WHERE id NOT IN (SELECT revaluation_id FROM price_revaluation_confirmations WHERE user_id = ?)
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $reval_id = (int)$stmt->fetchColumn();
}

// Если подтверждать нечего
if (!$reval_id) {
    echo '<div class="card" style="text-align:center; padding:50px;">
            <div style="font-size:50px;">✅</div>
            <h2>Все цены актуальны</h2>
            <p class="muted">Новых переоценок для ознакомления не найдено.</p>
            <a href="?page=dashboard" class="btn" style="display:inline-block; margin-top:20px;">Вернуться на главную</a>
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
    // ВАЖНО: замени is_user_on_shift на свою функцию проверки смены, если она другая
    $on_shift = 0; 
    if (isset($_SESSION['shift_id'])) $on_shift = 1;

    $stmt = $pdo->prepare("INSERT INTO price_revaluation_confirmations (revaluation_id, user_id, is_on_shift) VALUES (?, ?, ?)");
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
        background: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.3);
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
        <a href="pages/print_tags.php?id=<?= $reval_id ?>" target="_blank" class="btn" style="background: rgba(255,255,255,0.05); text-decoration: none;">🖨️ Печать</a>
    </div>

    <form method="POST">
        <table class="confirm-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Проверил</th>
                    <th>Товар</th>
                    <th>Старая цена</th>
                    <th>Новая цена</th>
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
                <b style="color: #ff6b6b; display: block; margin-bottom: 4px;">ЮРИДИЧЕСКОЕ ПОДТВЕРЖДЕНИЕ</b>
                <span class="muted" style="font-size: 13px;">
                    Я подтверждаю, что изучил новые цены. 
                    <?php if (isset($_SESSION['shift_id'])): ?>
                        Я нахожусь на смене и обязуюсь <b>немедленно</b> заменить физические ценники.
                    <?php endif; ?>
                </span>
            </label>
        </div>

        <button type="submit" name="confirm_all" class="btn" style="width: 100%; height: 55px; margin-top: 25px; background: #2ecc71; font-size: 16px; font-weight: 800;">
            🚀 ПОДТВЕРДИТЬ И ОБНОВИТЬ
        </button>
    </form>
</div>
