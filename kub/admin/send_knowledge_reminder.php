<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_admin();

$userId = (int)($_POST['user_id'] ?? 0);
$subId  = (int)($_POST['subtheme_id'] ?? 0);

if (!$userId || !$subId) {
    http_response_code(400);
    exit('Invalid request');
}

// получаем название инструкции
$stmt = $pdo->prepare("
    SELECT title
    FROM subthemes
    WHERE id = ?
");
$stmt->execute([$subId]);
$title = $stmt->fetchColumn();

if (!$title) {
    exit('Instruction not found');
}

// создаём уведомление
$pdo->prepare("
    INSERT INTO notifications (user_id, title, message, type, related_id)
    VALUES (?, ?, ?, 'manual_reminder', ?)
")->execute([
    $userId,
    'Напоминание',
    'Пожалуйста, ознакомьтесь с инструкцией: «'.$title.'»',
    $subId
]);

header('Location: /admin/knowledge_stats.php');
exit;