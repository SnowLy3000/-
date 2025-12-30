<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

$userId = $_SESSION['user']['id'] ?? 0;

if (!$userId) {
    exit('Нет пользователя');
}

$saleId = (int)($_POST['sale_id'] ?? 0);
$itemId = (int)($_POST['item_id'] ?? 0);

if (!$saleId || !$itemId) {
    header('Location: /cabinet/index.php?page=sales&sale_id='.$saleId);
    exit;
}

/* Проверяем, что чек принадлежит этому пользователю */
$stmt = $pdo->prepare("
    SELECT id
    FROM sales
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");
$stmt->execute([$saleId, $userId]);

if (!$stmt->fetch()) {
    exit('Нет доступа');
}

/* Удаляем товар */
$stmt = $pdo->prepare("
    DELETE FROM sale_items
    WHERE id = ?
      AND sale_id = ?
");
$stmt->execute([$itemId, $saleId]);

/* Пересчитываем сумму чека */
$stmt = $pdo->prepare("
    UPDATE sales
    SET total_amount = (
        SELECT COALESCE(SUM(price * quantity), 0)
        FROM sale_items
        WHERE sale_id = ?
    )
    WHERE id = ?
");
$stmt->execute([$saleId, $saleId]);

header('Location: /cabinet/index.php?page=sales&sale_id='.$saleId);
exit;