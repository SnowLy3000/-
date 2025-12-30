<?php
/**
 * admin/pages/price_confirm.php
 * Страница подтверждения ознакомления с новыми ценами
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// 1. Защита доступа
require_auth();
require_role('price_confirm');

if (!function_exists('h')) {
    function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}

// 2. Определение ID пользователя из сессии
$u_id = (int)($_SESSION['user']['id'] ?? 0);
if ($u_id <= 0) {
    die("<div class='card' style='color:red; padding:20px;'>Ошибка: Сессия не найдена. Пожалуйста, перезайдите.</div>");
}

// 3. Получаем ID переоценки из URL или ищем первый непрочитанный за последние 24 часа
$reval_id = (int)($_GET['id'] ?? 0);

if (!$reval_id) {
    // Ищем акт, созданный не более 24 часов назад, который юзер еще не подтвердил
    $stmt = $pdo->prepare("
        SELECT id FROM price_revaluations 
        WHERE created_at > NOW() - INTERVAL 1 DAY
        AND id NOT IN (SELECT revaluation_id FROM price_revaluation_confirmations WHERE user_id = ?)
        ORDER BY id ASC LIMIT 1
    ");
    $stmt->execute([$u_id]);
    $reval_id = (int)$stmt->fetchColumn();
}

// 4. Если подтверждать нечего — выводим сообщение об успехе
if (!$reval_id) {
    echo '<div class="card" style="text-align:center; padding: 80px 20px;">
            <div style="font-size: 80px; margin-bottom: 25px;">✅</div>
            <h2 style="margin:0; color: #7CFF6B;">Цены актуальны!</h2>
            <p class="muted" style="margin-top:10px; font-size:16px;">На данный момент новых актов переоценки для вас нет.</p>
            <a href="?page=dashboard" class="btn" style="margin-top:30px; display:inline-block; padding: 15px 35px; background: #785aff; color:#fff; text-decoration:none; border-radius:15px; font-weight:bold;">На главную</a>
          </div>';
    return;
}

/**
 * 5. ОБРАБОТКА ПОДТВЕРЖДЕНИЯ (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'])) {
    $is_on_shift = isset($_SESSION['shift_id']) ? 1 : 0;

    try {
        // Проверяем, не было ли уже подтверждения (защита от повторных кликов)
        $check = $pdo->prepare("SELECT id FROM price_revaluation_confirmations WHERE revaluation_id = ? AND user_id = ?");
        $check->execute([$reval_id, $u_id]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO price_revaluation_confirmations 
                (revaluation_id, user_id, is_on_shift, confirmed_at, created_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$reval_id, $u_id, $is_on_shift]);
        }
        
        // После успешной записи перенаправляем на Dashboard
        // Это принудительно обновит состояние и уберет уведомление
        echo "<script>window.location.href='index.php?page=dashboard&confirmed_reval=" . $reval_id . "';</script>";
        exit;
    } catch (PDOException $e) {
        die("Ошибка базы данных: " . $e->getMessage());
    }
}

// 6. Загружаем данные для отображения таблицы
$stmt = $pdo->prepare("
    SELECT ri.*, p.name 
    FROM price_revaluation_items ri 
    JOIN products p ON p.id = ri.product_id 
    WHERE ri.revaluation_id = ?
");
$stmt->execute([$reval_id]);
$items = $stmt->fetchAll();
?>

<style>
    .confirm-table { width: 100%; border-collapse: collapse; }
    .confirm-table th { text-align: left; padding: 12px; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); }
    .confirm-table td { padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .price-old { color: rgba(255,255,255,0.3); text-decoration: line-through; }
    .price-new { background: rgba(124, 255, 107, 0.1); color: #7CFF6B; padding: 4px 10px; border-radius: 8px; font-weight: 800; border: 1px solid rgba(124, 255, 107, 0.2); }
    .btn-confirm { width: 100%; height: 70px; font-size: 18px; font-weight: 800; background: #2ecc71; color: #fff; border: none; margin-top: 25px; border-radius: 20px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 30px rgba(46, 204, 113, 0.2); }
    .btn-confirm:hover { background: #27ae60; transform: translateY(-2px); box-shadow: 0 15px 35px rgba(46, 204, 113, 0.3); }
    .card-info { background: rgba(120,90,255,0.05); border: 1px solid rgba(120,90,255,0.2); border-radius: 20px; padding: 25px; margin-top: 20px; }
    .check-custom { width: 25px; height: 25px; accent-color: #785aff; cursor:pointer; }
</style>



<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 24px;">📄 Акт ознакомления №<?= h($reval_id) ?></h1>
            <p class="muted" style="margin-top:5px;">Пожалуйста, проверьте изменения цен на витрине</p>
        </div>
        <button class="btn" onclick="window.print()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">🖨️ Печать акта</button>
    </div>

    <form method="POST">
        <div style="border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; background: rgba(255,255,255,0.01);">
            <table class="confirm-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Старая цена</th>
                        <th>Новая цена</th>
                        <th style="text-align: right;">Проверено</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td><b style="color: #fff;"><?= h($it['name']) ?></b></td>
                        <td><span class="price-old"><?= number_format($it['old_price'], 2) ?> L</span></td>
                        <td><span class="price-new"><?= number_format($it['new_price'], 2) ?> L</span></td>
                        <td style="text-align: right;">
                            <input type="checkbox" required class="check-custom">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-info">
            <label style="display: flex; align-items: center; gap: 20px; cursor: pointer;">
                <input type="checkbox" required class="check-custom" style="width: 35px; height: 35px;">
                <span style="font-size: 15px; line-height: 1.4; color: #eee;">
                    <b>Я подтверждаю замену ценников.</b><br>
                    Ознакомлен с новыми ценами и обязуюсь продавать товар по указанной стоимости.
                </span>
            </label>
        </div>

        <button type="submit" name="confirm_action" class="btn-confirm">
            ✅ ПОДТВЕРДИТЬ И ОБНОВИТЬ КАССУ
        </button>
    </form>
</div>
