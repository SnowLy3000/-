<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_auth();

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Ищем товары в базе по названию
$stmt = $pdo->prepare("SELECT name, price FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 15");
$stmt->execute(['%' . $q . '%']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
