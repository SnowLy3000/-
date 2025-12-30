<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/perms.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на добавление позиций в чек
require_role('sale_item_add');

$saleId    = (int)($_POST['sale_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);
$price     = (float)($_POST['price'] ?? 0);
$qty       = max(1, (int)($_POST['quantity'] ?? 1));

if (!$saleId || !$productId || $price <= 0) {
    header('Location: /admin/index.php?page=sales&error=invalid_data');
    exit;
}

try {
    /* 🔹 3. Получаем процент по товару и проверяем его существование */
    $stmt = $pdo->prepare("
        SELECT c.percent, p.name
        FROM products p
        JOIN salary_categories c ON c.id = p.category_id
        WHERE p.id = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        die("Ошибка: Товар не найден или деактивирован.");
    }

    $percent = (float)$product['percent'];
    // Расчет суммы, которая пойдет в KPI/Зарплату сотрудника за эту позицию
    $salary = ($price * $qty) * ($percent / 100);

    /* 🔹 4. Добавляем позицию в таблицу элементов продажи */
    $stmt = $pdo->prepare("
        INSERT INTO sale_items
        (sale_id, product_id, quantity, price, percent, salary_amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $saleId,
        $productId,
        $qty,
        $price,
        $percent,
        $salary
    ]);

    // Возвращаемся в интерфейс продажи
    header('Location: /admin/index.php?page=sales&sale_id='.$saleId.'&success=item_added');
    exit;

} catch (PDOException $e) {
    die("Ошибка при добавлении товара в чек: " . $e->getMessage());
}