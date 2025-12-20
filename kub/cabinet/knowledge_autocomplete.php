<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';

$result = [];

// Темы
$stmt = $pdo->prepare("
    SELECT id, title
    FROM themes
    WHERE title LIKE ?
    LIMIT 5
");
$stmt->execute([$like]);

foreach ($stmt as $row) {
    $result[] = [
        'type' => 'theme',
        'title' => $row['title'],
        'url' => '/cabinet/knowledge_view.php?theme=' . $row['id']
    ];
}

// Подтемы
$stmt = $pdo->prepare("
    SELECT s.id, s.title, s.theme_id
    FROM subthemes s
    WHERE s.title LIKE ? OR s.content LIKE ?
    LIMIT 5
");
$stmt->execute([$like, $like]);

foreach ($stmt as $row) {
    $result[] = [
        'type' => 'subtheme',
        'title' => $row['title'],
        'url' => '/cabinet/knowledge_view.php?theme=' . $row['theme_id']
    ];
}

header('Content-Type: application/json');
echo json_encode($result);