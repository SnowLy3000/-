<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

// Получаем запрос
$q = trim($_GET['q'] ?? '');

// Если пусто — отдаем пустой массив
if (mb_strlen($q) < 1) { 
    echo json_encode([]); 
    exit; 
}

// Ищем товары. Важно: добавили p.price и p.brand
$stmt = $pdo->prepare("
    SELECT 
        p.name, 
        p.price, 
        p.brand, 
        c.percent 
    FROM products p 
    LEFT JOIN salary_categories c ON c.id = p.category_id 
    WHERE p.name LIKE ? OR p.brand LIKE ?
    LIMIT 15
");

$stmt->execute(['%' . $q . '%', '%' . $q . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Отправляем JSON
header('Content-Type: application/json');
echo json_encode($results);
