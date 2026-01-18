<?php
// 1. ПОДКЛЮЧАЕМ СИСТЕМУ (Проверь, что пути ведут в твою папку includes)
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php'; // ДОБАВИЛИ ЭТУ СТРОКУ

// 2. ПРОВЕРКА ПРАВ
$user = current_user();
// Проверяем через has_role или напрямую через проверку в БД
if (!$user || (!has_role('Admin') && !has_role('Owner'))) {
    die("Доступ запрещен. У вас нет прав Администратора.");
}

// 3. ОБРАБОТКА ДАННЫХ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($title)) {
        die("Название темы не может быть пустым");
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE academy_topics SET title = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$title, $description, $sort_order, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO academy_topics (title, description, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $sort_order]);
        }

        header('Location: /admin/index.php?page=academy_manage&success=1');
        exit;

    } catch (PDOException $e) {
        die("Ошибка базы данных: " . $e->getMessage());
    }
}
