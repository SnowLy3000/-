<?php  
require_once __DIR__.'/../../includes/auth.php';  
require_once __DIR__.'/../../includes/db.php';  
  
require_auth();  
  
$userId   = (int)$_SESSION['user']['id'];  
$saleId   = (int)($_POST['sale_id'] ?? 0);  
$name     = trim($_POST['product_name'] ?? '');  
$brand    = trim($_POST['brand'] ?? '');  
$price    = (float)($_POST['price'] ?? 0);  
$qty      = (int)($_POST['quantity'] ?? 1);  
$discount = (float)($_POST['discount'] ?? 0);  
  
if (!$saleId || $name === '' || $price <= 0 || $qty <= 0) {  
    exit('Invalid data');  
}  
  
/* ================= 1. ПОИСК ТОВАРА И ПРОЦЕНТА ================= */  
$percent = null;  
  
$stmt = $pdo->prepare("  
    SELECT c.percent  
    FROM products p  
    JOIN salary_categories c ON c.id = p.category_id  
    WHERE p.name = ?  
      AND p.is_active = 1  
      AND c.is_active = 1  
    LIMIT 1  
");  
$stmt->execute([$name]);  
$row = $stmt->fetch();  
  
if ($row) {  
    $percent = (float)$row['percent'];  
}  
  
/* ================= 2. РАСЧЁТ ЗАРПЛАТЫ ================= */  
// Цена за 1 шт со скидкой
$singlePriceWithDiscount = $price - ($price * $discount / 100);
// Общая сумма за все количество
$totalRowSum = round($singlePriceWithDiscount * $qty, 2);
$totalRowSum = max(0, $totalRowSum);  
  
$salaryAmount = 0;  
if ($percent !== null) {  
    // Считаем заработок и округляем до 2 знаков
    $salaryAmount = round($totalRowSum * ($percent / 100), 2);  
}  
  
/* ================= 3. СОХРАНЕНИЕ В ЧЕК ================= */  
try {
    $stmt = $pdo->prepare("  
        INSERT INTO sale_items (  
            sale_id,  
            product_name,  
            brand,  
            price,  
            quantity,  
            discount,  
            percent,  
            salary_amount,  
            is_discounted  
        ) VALUES (  
            ?, ?, ?, ?, ?, ?, ?, ?, ?  
        )  
    ");  
  
    $stmt->execute([  
        $saleId,  
        $name,  
        $brand,  
        $price,  
        $qty,  
        $discount,  
        $percent,  
        $salaryAmount,  
        $discount > 0 ? 1 : 0  
    ]);  

    // Редирект обратно в чек
    header("Location: /cabinet/index.php?page=sales&sale_id=".$saleId);  

} catch (Exception $e) {
    // Если база данных выдаст ошибку (например, нет какой-то колонки)
    exit("Database Error: " . $e->getMessage());
}
exit;
