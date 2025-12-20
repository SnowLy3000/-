<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/notifications.php';

require_admin();
require_permission('TEST_MANAGE');

/**
 * ============================
 * АВТО-УДАЛЕНИЕ СТАРЫХ АКЦИЙ
 * ============================
 * Удаляем навсегда, если прошло >24ч
 */
$pdo->query("
    DELETE FROM actions
    WHERE deleted_at IS NOT NULL
      AND delete_after IS NOT NULL
      AND delete_after <= NOW()
");

/**
 * ============================
 * УДАЛЕНИЕ (soft delete)
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'delete') {
    $id = (int)$_POST['id'];

    $pdo->prepare("
        UPDATE actions
        SET
            deleted_at = NOW(),
            delete_after = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE id = ?
          AND deleted_at IS NULL
    ")->execute([$id]);

    header('Location: actions.php');
    exit;
}

/**
 * ============================
 * ВОССТАНОВЛЕНИЕ
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'restore') {
    $id = (int)$_POST['id'];

    $pdo->prepare("
        UPDATE actions
        SET deleted_at = NULL, delete_after = NULL
        WHERE id = ?
          AND deleted_at IS NOT NULL
          AND delete_after > NOW()
    ")->execute([$id]);

    header('Location: actions.php?show_deleted=1');
    exit;
}

/**
 * ============================
 * СОЗДАНИЕ АКЦИИ
 * ============================
 */
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'add') {

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'action';
    $important = isset($_POST['is_important']) ? 1 : 0;
    $required = isset($_POST['is_required']) ? 1 : 0;
    $dueAt = $_POST['due_at'] ?: null;

    if ($title === '' || $content === '') {
        $message = 'Название и текст обязательны';
    } else {
        $pdo->prepare("
            INSERT INTO actions
            (title, content, type, is_important, is_required, due_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $title,
            $content,
            $type,
            $important,
            $required,
            $dueAt,
            $_SESSION['user']['id']
        ]);

        $message = 'Создано';
    }
}

/**
 * ============================
 * СПИСОК АКЦИЙ
 * ============================
 */
$showDeleted = isset($_GET['show_deleted']);

$actions = $pdo->query("
    SELECT *
    FROM actions
    " . ($showDeleted ? "WHERE deleted_at IS NOT NULL" : "WHERE deleted_at IS NULL") . "
    ORDER BY created_at DESC
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Акции и инструкции</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<script src="https://cdn.tiny.cloud/1/zufq95qlrqvk7gxmrsptp6rkuk4ivm1evmx1888qvqv33ami/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#content',
    height: 260,
    menubar: false,
    plugins: 'lists link image code',
    toolbar: 'undo redo | bold italic underline | bullist numlist | link image | code'
});
</script>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/actions.php"><b>📢 Акции</b></a>
    <a href="/admin/actions_stats.php">📊 Статистика</a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📢 Акции и инструкции</h1>

<?php if ($message): ?>
<p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<div class="card neon">
<h3>Создать</h3>
<form method="post">
<input type="hidden" name="do" value="add">

<input name="title" placeholder="Название">

<select name="type">
    <option value="action">Акция</option>
    <option value="instruction">Инструкция</option>
    <option value="price_change">Замена цен</option>
    <option value="cross_sale">Кросс-продажа</option>
</select>

<textarea id="content" name="content"></textarea>

<label>Выполнить до:
<input type="date" name="due_at">
</label><br><br>

<label><input type="checkbox" name="is_important"> Важно</label><br>
<label><input type="checkbox" name="is_required"> Обязательно</label><br><br>

<button class="btn">Создать</button>
</form>
</div>

<h3 style="margin-top:30px;">
<?= $showDeleted ? 'Удалённые (можно восстановить)' : 'Активные' ?>
</h3>

<a href="?<?= $showDeleted ? '' : 'show_deleted=1' ?>">
<?= $showDeleted ? '← Активные' : 'Показать удалённые' ?>
</a>

<?php foreach ($actions as $a): ?>
<div class="card neon">
<b><?= htmlspecialchars($a['title']) ?></b>

<?php if ($a['deleted_at']): ?>
    <div style="color:#ff6666">
        ⏳ Будет удалено: <?= $a['delete_after'] ?>
    </div>

    <form method="post">
        <input type="hidden" name="do" value="restore">
        <input type="hidden" name="id" value="<?= $a['id'] ?>">
        <button class="btn">↩ Восстановить</button>
    </form>

<?php else: ?>

    <form method="post" onsubmit="return confirm('Удалить? Можно будет восстановить 24 часа')">
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="<?= $a['id'] ?>">
        <button class="btn btn-danger">🗑 Удалить</button>
    </form>

<?php endif; ?>
</div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>
