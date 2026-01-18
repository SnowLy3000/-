<?php
// 1. ПОДКЛЮЧАЕМ СИСТЕМНЫЕ ФАЙЛЫ
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php'; // ОБЯЗАТЕЛЬНО для has_role()

// 2. ПРОВЕРКА ДОСТУПА
$user = current_user();
if (!$user || (!has_role('Admin') && !has_role('Owner'))) {
    die("Доступ запрещен. Недостаточно прав.");
}

// 3. ОБРАБОТКА ДАННЫХ ИЗ ФОРМЫ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $topic_id = (int)$_POST['topic_id'];
    $title = trim($_POST['title']);
    $content = $_POST['content']; // Текст урока (HTML из редактора)
    $video_url = trim($_POST['video_url']);
    $sort_order = (int)$_POST['sort_order'];

    if (empty($title) || $topic_id <= 0) {
        die("Ошибка: Название урока и тема обязательны.");
    }

    try {
        if ($id > 0) {
            // ОБНОВЛЕНИЕ СУЩЕСТВУЮЩЕГО УРОКА
            $stmt = $pdo->prepare("UPDATE academy_lessons SET topic_id = ?, title = ?, content = ?, video_url = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$topic_id, $title, $content, $video_url, $sort_order, $id]);
        } else {
            // СОЗДАНИЕ НОВОГО УРОКА
            $stmt = $pdo->prepare("INSERT INTO academy_lessons (topic_id, title, content, video_url, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$topic_id, $title, $content, $video_url, $sort_order]);
        }

        // Возвращаемся в управление темой
        header("Location: /admin/index.php?page=academy_manage&success=lesson_saved");
        exit;

    } catch (PDOException $e) {
        die("Ошибка базы данных: " . $e->getMessage());
    }
}
