<?php
/**
 * admin/pages/price_confirm.php
 * Исправленная версия: новые сотрудники не видят старые переоценки
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('price_confirm');

if (!function_exists('h')) {
    function h($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}

$u_id = (int)($_SESSION['user']['id'] ?? 0);
if ($u_id <= 0) die("Ошибка авторизации.");

// 1. ПОЛУЧАЕМ ДОЛЖНОСТЬ И ДАТУ РЕГИСТРАЦИИ ЧЕРЕЗ JOIN
// Так как position_id находится в таблице user_positions
$stmtUser = $pdo->prepare("
    SELECT u.created_at, up.position_id 
    FROM users u 
    LEFT JOIN user_positions up ON u.id = up.user_id 
    WHERE u.id = ? 
    LIMIT 1
");
$stmtUser->execute([$u_id]);
$userData = $stmtUser->fetch();

$u_pos_id = (int)($userData['position_id'] ?? 0);
$u_reg_date = $userData['created_at'] ?? '2000-01-01 00:00:00';

$reval_id = (int)($_GET['id'] ?? 0);

// 2. Поиск актуального акта, если ID не передан
if (!$reval_id) {
    // Выбираем акты за последние 24 часа, созданные ПОСЛЕ регистрации пользователя
    $stmt = $pdo->prepare("
        SELECT id, target_positions 
        FROM price_revaluations 
        WHERE created_at > NOW() - INTERVAL 1 DAY 
          AND created_at >= ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$u_reg_date]);
    $recent_acts = $stmt->fetchAll();

    foreach ($recent_acts as $act) {
        $targets = json_decode((string)$act['target_positions'], true);
        $is_for_me = empty($targets) || in_array($u_pos_id, $targets);
        
        if ($is_for_me) {
            $check = $pdo->prepare("SELECT id FROM price_revaluation_confirmations WHERE revaluation_id = ? AND user_id = ?");
            $check->execute([$act['id'], $u_id]);
            if (!$check->fetch()) { 
                $reval_id = $act['id']; 
                break; 
            }
        }
    }
}

// 3. Если подтверждать нечего
if (!$reval_id) {
    echo '<div style="text-align:center; padding: 100px 20px;">
            <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
            <h2 style="margin:0; font-weight:900;">Цены актуальны</h2>
            <p style="opacity:0.5; margin-top:10px;">Для вас новых переоценок не найдено.</p>
            <a href="?page=dashboard" class="btn" style="margin-top:25px; display:inline-block; background:#785aff; padding:12px 30px; border-radius:12px; text-decoration:none; color:#fff; font-weight:700;">На главную</a>
          </div>';
    return;
}

// 4. Обработка подтверждения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action'])) {
    $is_on_shift = isset($_SESSION['shift_id']) ? 1 : 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO price_revaluation_confirmations (revaluation_id, user_id, is_on_shift, confirmed_at, created_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$reval_id, $u_id, $is_on_shift]);
    echo "<script>window.location.href='index.php?page=dashboard';</script>";
    exit;
}

$stmt = $pdo->prepare("SELECT ri.*, p.name FROM price_revaluation_items ri JOIN products p ON p.id = ri.product_id WHERE ri.revaluation_id = ?");
$stmt->execute([$reval_id]);
$items = $stmt->fetchAll();
?>

<style>
    .pc-container { max-width: 700px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; }
    .pc-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.01); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
    .pc-table th { text-align: left; padding: 12px 15px; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); }
    .pc-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    .old-p { color: rgba(255,255,255,0.2); text-decoration: line-through; font-size: 12px; }
    .new-p { color: #7CFF6B; font-weight: 900; }
    .confirm-box { background: rgba(120,90,255,0.05); border: 1px solid rgba(120,90,255,0.2); border-radius: 20px; padding: 20px; margin-top: 20px; }
    .btn-submit { width: 100%; height: 56px; background: #2ecc71; color: #fff; border: none; border-radius: 16px; font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.2s; margin-top: 20px; }
    .btn-submit:hover { background: #27ae60; transform: translateY(-2px); }
    .custom-check { width: 20px; height: 20px; accent-color: #785aff; cursor: pointer; }
    @media print { .no-print { display: none; } body { background: #fff; color: #000; } }
</style>

<div class="pc-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 22px; font-weight: 900;">Акт переоценки №<?= $reval_id ?></h1>
            <p style="margin:5px 0 0 0; font-size: 13px; opacity: 0.5;">Подтвердите физическую замену ценников</p>
        </div>
        <button onclick="window.print()" class="no-print" style="background:none; border: 1px solid #333; color:#fff; padding: 8px 15px; border-radius: 10px; font-size:12px; cursor:pointer;">🖨️ Печать</button>
    </div>

    <form method="POST">
        <table class="pc-table">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Старая цена</th>
                    <th>Новая цена</th>
                    <th style="text-align: right;">ОК</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td><b><?= h($it['name']) ?></b></td>
                    <td><span class="old-p"><?= number_format($it['old_price'], 0) ?> L</span></td>
                    <td><span class="new-p"><?= number_format($it['new_price'], 0) ?> L</span></td>
                    <td style="text-align: right;">
                        <input type="checkbox" required class="custom-check">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="confirm-box">
            <label style="display: flex; align-items: flex-start; gap: 15px; cursor: pointer;">
                <input type="checkbox" required class="custom-check" style="margin-top: 3px;">
                <span style="font-size: 13px; line-height: 1.5;">
                    <b>Я подтверждаю замену ценников в торговом зале.</b><br>
                    Обязуюсь соблюдать дисциплину цен и продавать товар согласно данному акту.
                </span>
            </label>
        </div>

        <button type="submit" name="confirm_action" class="btn-submit">ПОДТВЕРДИТЬ ИЗМЕНЕНИЯ</button>
    </form>
</div>