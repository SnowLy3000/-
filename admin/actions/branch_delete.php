<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

// Проверяем авторизацию
require_auth();

// Проверяем наличие конкретного права на удаление филиалов
require_role('branch_delete');

// Удаление должно происходить только через POST запрос для безопасности
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            // Если есть связанные данные (смены, продажи), база выдаст ошибку
            die("Ошибка при удалении. К этому филиалу привязаны сотрудники, смены или продажи. Сначала удалите связанные записи.");
        }
    }
}

// Возвращаемся на страницу филиалов
header('Location: /admin/index.php?page=branches');
exit;