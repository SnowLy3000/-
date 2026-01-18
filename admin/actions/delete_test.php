<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

$user = current_user();
if (!$user || (!has_role('Admin') && !has_role('Owner'))) { 
    die("Доступ запрещен"); 
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Удаляем тест (вопросы удалятся сами благодаря ON DELETE CASCADE в базе)
    $stmt = $pdo->prepare("DELETE FROM academy_tests WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: /admin/index.php?page=academy_manage&deleted=1");
exit;