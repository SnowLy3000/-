<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Проверка прав (если функция can_user настроена)
if (!has_role('Admin') && !has_role('Owner')) {
    echo "<div class='card'>У вас нет прав для управления академией.</div>";
    return;
}

$topic = null;
$id = (int)($_GET['id'] ?? 0);

// Если передано ID, значит мы в режиме редактирования
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM academy_topics WHERE id = ?");
    $stmt->execute([$id]);
    $topic = $stmt->fetch();
}
?>

<div style="max-width: 700px; margin: 0 auto;">
    <div style="margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="margin: 0; color: #fff;"><?= $topic ? '✏️ Редактировать тему' : '📁 Создать новую тему' ?></h2>
            <p style="opacity: 0.5; font-size: 13px; margin: 5px 0 0 0;">Темы объединяют уроки в общие разделы</p>
        </div>
        <a href="?page=academy_manage" class="btn" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">← Назад</a>
    </div>

    <form action="/admin/actions/academy_topic_save.php" method="POST" style="background: #16161a; padding: 30px; border-radius: 24px; border: 1px solid #222;">
        <?php if ($topic): ?>
            <input type="hidden" name="id" value="<?= $topic['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px; letter-spacing: 1px;">Название темы</label>
            <input type="text" name="title" value="<?= htmlspecialchars($topic['title'] ?? '') ?>" 
                   style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px; font-size: 15px;" 
                   placeholder="Например: Основы продаж или Техника безопасности" required>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px; letter-spacing: 1px;">Описание</label>
            <textarea name="description" 
                      style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px; height: 100px; font-family: inherit; font-size: 14px;" 
                      placeholder="О чем этот раздел?"><?= htmlspecialchars($topic['description'] ?? '') ?></textarea>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; font-size: 11px; color: #785aff; text-transform: uppercase; font-weight: 800; margin-bottom: 8px; letter-spacing: 1px;">Порядок (сортировка)</label>
            <input type="number" name="sort_order" value="<?= $topic['sort_order'] ?? 0 ?>" 
                   style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 12px; font-size: 15px;">
            <small style="opacity: 0.3; display: block; margin-top: 5px;">Чем меньше число, тем выше тема в списке</small>
        </div>

        <button type="submit" class="btn" style="width: 100%; justify-content: center; padding: 16px; font-size: 14px; letter-spacing: 1px;">
            <?= $topic ? 'СОХРАНИТЬ ИЗМЕНЕНИЯ' : 'СОЗДАТЬ ТЕМУ ОБУЧЕНИЯ' ?>
        </button>
    </form>
</div>
