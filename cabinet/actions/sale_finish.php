<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

$saleId   = (int)($_POST['sale_id'] ?? 0);
$payment  = trim($_POST['payment_type'] ?? 'cash');

// Новые данные из формы
$clientId       = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
$discountAmount = !empty($_POST['final_discount_amount']) ? (float)$_POST['final_discount_amount'] : 0;

if (!$saleId) exit('Ошибка: ID чека не передан');

// Проверяем доступ к чеку
$stmt = $pdo->prepare("SELECT id FROM sales WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$saleId, $userId]);
if (!$stmt->fetch()) exit('Нет доступа к этому чеку');

// 1. Считаем грязную сумму (сумма товаров в корзине до клиентской скидки)
$stmt = $pdo->prepare("SELECT SUM(CEIL(price - (price * discount / 100)) * quantity) FROM sale_items WHERE sale_id = ?");
$stmt->execute([$saleId]);
$subtotal = (float)($stmt->fetchColumn() ?: 0);

// 2. Вычисляем финальную сумму к оплате (грязная сумма минус скидка клиента)
$finalTotal = $subtotal - $discountAmount;

// 3. Закрываем чек: записываем клиента, итоговую сумму, сумму скидки и тип оплаты
$stmt = $pdo->prepare("
    UPDATE sales 
    SET client_id = ?, 
        payment_type = ?, 
        total_amount = ?, 
        discount_amount = ?, 
        created_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$clientId, $payment, $finalTotal, $discountAmount, $saleId]);

// Если клиент был указан, можно обновить его общую сумму покупок (для истории)
if ($clientId) {
    $stmt = $pdo->prepare("UPDATE clients SET total_bought = total_bought + ? WHERE id = ?");
    $stmt->execute([$finalTotal, $clientId]);
}

// Возвращаемся в раздел продаж
header('Location: /cabinet/index.php?page=sales');
exit;
