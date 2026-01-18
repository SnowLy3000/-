<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_auth();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

$today = date('Y-m-d');

// Ищем товар + проверяем наличие активной акции
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.name, 
        p.price as original_price,
        pr.promo_price,
        pr.id as has_promo
    FROM products p 
    LEFT JOIN product_promotions pr ON 
        pr.product_name = p.name 
        AND :today BETWEEN pr.start_date AND pr.end_date
    WHERE p.name LIKE :search AND p.is_active = 1
    LIMIT 15
");

$stmt->execute([
    'today' => $today,
    'search' => '%' . $q . '%'
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Форматируем ответ для JavaScript
foreach ($results as &$row) {
    if ($row['has_promo']) {
        $row['price'] = $row['promo_price'];
        $row['is_promo'] = true; // Флаг, что это акция
    } else {
        $row['price'] = $row['original_price'];
        $row['is_promo'] = false;
    }
}

echo json_encode($results);
