<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT p.name, c.percent 
    FROM products p 
    JOIN salary_categories c ON c.id = p.category_id 
    WHERE p.name LIKE ? 
    LIMIT 10
");
$stmt->execute(['%' . $q . '%']);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
