<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);

// Получаем данные из POST
$saleId  = (int)($_POST['sale_id'] ?? 0);
$source  = trim($_POST['client_source'] ?? 'Не указано'); // Даем значение по умолчанию, если пусто
$payment = trim($_POST['payment_type'] ?? 'cash');

// Если ID чека нет - это действительно ошибка
if (!$saleId) {
    exit('Ошибка: ID чека не передан');
}

/* 1. Проверяем доступ к чеку */
$stmt = $pdo->prepare("SELECT id FROM sales WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$saleId, $userId]);
if (!$stmt->fetch()) {
    exit('Нет доступа к этому чеку');
}

/* 2. Считаем финальную сумму чека по всем позициям (sale_items) */
// Это гарантирует, что в таблицу sales запишется правильный total_amount
$stmt = $pdo->prepare("
    SELECT SUM(CEIL(price - (price * discount / 100)) * quantity) as real_total 
    FROM sale_items 
    WHERE sale_id = ?
");
$stmt->execute([$saleId]);
$totalAmount = (float)($stmt->fetchColumn() ?: 0);

/* 3. Финализируем чек */
$stmt = $pdo->prepare("
    UPDATE sales
    SET client_source = ?, 
        payment_type = ?, 
        total_amount = ?,
        created_at = NOW() -- Обновляем время на реальное время продажи
    WHERE id = ?
");

$stmt->execute([$source, $payment, $totalAmount, $saleId]);

/* 4. Редирект обратно на продажи (откроется новый чистый чек) */
header('Location: /cabinet/index.php?page=sales');
exit;
