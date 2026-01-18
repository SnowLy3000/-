<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';

if (strlen($phone) < 3) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, discount_percent FROM clients WHERE phone LIKE ? LIMIT 1");
$stmt->execute([$phone . '%']);
$client = $stmt->fetch();

if ($client) {
    echo json_encode([
        'success' => true,
        'id' => $client['id'],
        'name' => $client['name'],
        'discount' => $client['discount_percent']
    ]);
} else {
    echo json_encode(['success' => false]);
}
