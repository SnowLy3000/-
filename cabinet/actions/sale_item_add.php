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
  
if ($name === '' || $price <= 0 || $qty <= 0) {  
    exit('Invalid data');  
}  

/* ================= 0. ЕСЛИ SALE_ID НЕТ — СОЗДАЕМ ЧЕК ================= */
if ($saleId === 0) {
    // Получаем branch_id из активной смены
    $stmtBranch = $pdo->prepare("SELECT branch_id FROM shift_sessions WHERE user_id = ? AND checkout_at IS NULL ORDER BY checkin_at DESC LIMIT 1");
    $stmtBranch->execute([$userId]);
    $branchId = (int)($stmtBranch->fetchColumn() ?: 0);

    if ($branchId === 0) exit('Ошибка: Смена не открыта');

    $stmtCreate = $pdo->prepare("INSERT INTO sales (user_id, branch_id, payment_type, total_amount, created_at) VALUES (?, ?, 'cash', 0.00, NOW())");
    $stmtCreate->execute([$userId, $branchId]);
    $saleId = $pdo->lastInsertId();
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
if ($row) $percent = (float)$row['percent'];  
  
/* ================= 2. РАСЧЁТ ЗАРПЛАТЫ ================= */  
$singlePriceWithDiscount = $price - ($price * $discount / 100);
$totalRowSum = round($singlePriceWithDiscount * $qty, 2);
$totalRowSum = max(0, $totalRowSum);  
  
$salaryAmount = 0;  
if ($percent !== null) {  
    $salaryAmount = round($totalRowSum * ($percent / 100), 2);  
}  
  
/* ================= 3. СОХРАНЕНИЕ В ЧЕК ================= */  
try {
    $stmt = $pdo->prepare("  
        INSERT INTO sale_items (sale_id, product_name, brand, price, quantity, discount, percent, salary_amount, is_discounted) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)  
    ");  
  
    $stmt->execute([  
        $saleId, $name, $brand, $price, $qty, $discount, $percent, $salaryAmount, $discount > 0 ? 1 : 0  
    ]);  

    header("Location: /cabinet/index.php?page=sales&sale_id=".$saleId);  
} catch (Exception $e) {
    exit("Database Error: " . $e->getMessage());
}
exit;
