<?php
require __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$theme = $_POST['theme'] ?? 'light';
$theme = ($theme === 'dark') ? 'dark' : 'light';

// сохраняем в сессию гостя
$_SESSION['guest_theme'] = $theme;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'theme'=>$theme]);