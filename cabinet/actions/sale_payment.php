<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

$saleId = (int)($_POST['sale_id'] ?? 0);
$pay = $_POST['payment_type'] ?? 'cash';

$allowed = ['cash','card','invoice'];
if (!$saleId || !in_array($pay,$allowed)) {
    exit('Ошибка');
}

$pdo->prepare("
    UPDATE sales
    SET payment_type = ?
    WHERE id = ?
")->execute([$pay, $saleId]);

header('Location: /cabinet/index.php?page=sales');
exit;