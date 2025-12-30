<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

$itemId   = (int)($_POST['item_id'] ?? 0);
$saleId   = (int)($_POST['sale_id'] ?? 0);
$price    = (float)($_POST['price'] ?? 0);
$discount = (float)($_POST['discount'] ?? 0);
$qty      = (int)($_POST['quantity'] ?? 1);

if (!$itemId || !$saleId || $price <= 0 || $qty < 1) {
    header('Location: /cabinet/index.php?page=sales&sale_id='.$saleId);
    exit;
}

/* 1. Проверка доступа к чеку и товару */
$stmt = $pdo->prepare("
    SELECT s.id
    FROM sales s
    JOIN sale_items i ON i.sale_id = s.id
    WHERE s.id = ?
      AND s.user_id = ?
      AND i.id = ?
    LIMIT 1
");
$stmt->execute([$saleId, $userId, $itemId]);

if (!$stmt->fetch()) {
    exit('Нет доступа к редактированию этого товара');
}

/* 2. Обновляем данные конкретного товара */
$stmt = $pdo->prepare("
    UPDATE sale_items
    SET price = ?, discount = ?, quantity = ?
    WHERE id = ? AND sale_id = ?
");
$stmt->execute([$price, $discount, $qty, $itemId, $saleId]);

/* 3. ПЕРЕСЧЕТ ИТОГО ЧЕКА (Синхронизация с историей) */
// Выбираем все товары этого чека, чтобы посчитать честную сумму
$stmt = $pdo->prepare("SELECT price, discount, quantity FROM sale_items WHERE sale_id = ?");
$stmt->execute([$saleId]);
$allItems = $stmt->fetchAll();

$newTotal = 0;
foreach ($allItems as $item) {
    // Применяем логику округления 1С (ceil) и скидку
    $priceWithDiscount = ceil($item['price'] - ($item['price'] * $item['discount'] / 100));
    $newTotal += ($priceWithDiscount * $item['quantity']);
}

// Обновляем итоговое значение в главной таблице продаж
$updateSale = $pdo->prepare("UPDATE sales SET total_amount = ? WHERE id = ?");
$updateSale->execute([$newTotal, $saleId]);

/* 4. Возвращаемся в интерфейс продаж */
header('Location: /cabinet/index.php?page=sales&sale_id='.$saleId);
exit;
