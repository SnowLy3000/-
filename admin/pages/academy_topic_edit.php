<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

if (!has_role('Admin') && !has_role('Owner')) {
    echo "Нет доступа"; return;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM academy_topics WHERE id = ?");
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) { echo "Тема не найдена"; return; }
?>

<div style="max-width: 700px; margin: 0 auto;">
    <div style="margin-bottom: 25px;">
        <a href="?page=academy_manage" style="color: #785aff; text-decoration: none;">← Назад</a>
        <h2 style="color: #fff; margin-top: 10px;">✏️ Редактировать: <?= htmlspecialchars($topic['title']) ?></h2>
    </div>

    <form action="/admin/actions/academy_topic_save.php" method="POST" style="background: #16161a; padding: 30px; border-radius: 24px; border: 1px solid #222;">
        <input type="hidden" name="id" value="<?= $topic['id'] ?>">

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px;">Название темы</label>
            <input type="text" name="title" value="<?= htmlspecialchars($topic['title']) ?>" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px;" required>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px;">Описание</label>
            <textarea name="description" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px; height: 100px;"><?= htmlspecialchars($topic['description']) ?></textarea>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px;">Порядок</label>
            <input type="number" name="sort_order" value="<?= $topic['sort_order'] ?>" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px;">
        </div>

        <button type="submit" class="btn" style="width: 100%; padding: 16px;">СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
    </form>
</div>
