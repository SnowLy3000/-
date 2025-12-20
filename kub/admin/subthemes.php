<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$message = null;

/**
 * СОЗДАНИЕ / ОБНОВЛЕНИЕ ПОДТЕМЫ
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'] ?? null;
    $themeId  = (int)($_POST['theme_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $content  = $_POST['content'] ?? '';

    if (!$themeId || $title === '') {
        $message = 'Выберите тему и укажите название подтемы';
    } else {
        if ($id) {
            $pdo->prepare("
                UPDATE subthemes
                SET theme_id = ?, title = ?, content = ?
                WHERE id = ?
            ")->execute([$themeId, $title, $content, (int)$id]);

            $message = 'Подтема обновлена';
        } else {
            $pdo->prepare("
                INSERT INTO subthemes (theme_id, title, content)
                VALUES (?, ?, ?)
            ")->execute([$themeId, $title, $content]);

            $message = 'Подтема создана';
        }
    }
}

// Темы
$themes = $pdo->query("
    SELECT id, title
    FROM themes
    ORDER BY title
")->fetchAll();

// Редактирование
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM subthemes WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

// Подтемы
$subthemes = $pdo->query("
    SELECT s.id, s.title, t.title AS theme
    FROM subthemes s
    JOIN themes t ON t.id = s.theme_id
    ORDER BY t.title, s.title
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Подтемы</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/zufq95qlrqvk7gxmrsptp6rkuk4ivm1evmx1888qvqv33ami/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: 'textarea[name=content]',
    height: 380,
    menubar: true,
    plugins: 'lists link image table code media',
    toolbar:
        'undo redo | styles | bold italic underline | ' +
        'alignleft aligncenter alignright | bullist numlist | ' +
        'link image media | table | code',
    branding: false
});
</script>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/themes.php">Темы</a>
    <a href="/admin/subthemes.php"><b>Подтемы</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>Подтемы — база знаний</h1>

<?php if ($message): ?>
    <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- ФОРМА -->
<div class="card neon">
    <h3><?= $edit ? 'Редактировать подтему' : 'Создать подтему' ?></h3>

    <form method="post">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <?php endif; ?>

        <label>
            Тема:
            <select name="theme_id">
                <option value="">— выберите тему —</option>
                <?php foreach ($themes as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"
                        <?= ($edit && $edit['theme_id'] == $t['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <input
            name="title"
            placeholder="Название подтемы"
            value="<?= htmlspecialchars($edit['title'] ?? '') ?>"
        >

        <textarea name="content"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>

        <button class="btn">
            <?= $edit ? 'Сохранить' : 'Создать' ?>
        </button>
    </form>
</div>

<!-- СПИСОК -->
<h3 style="margin-top:30px;">Существующие подтемы</h3>

<?php if (!$subthemes): ?>
    <p>Подтем пока нет.</p>
<?php endif; ?>

<?php foreach ($subthemes as $s): ?>
    <div class="card neon" style="margin-bottom:10px;">
        <b><?= htmlspecialchars($s['title']) ?></b>
        <div style="opacity:.7;">Тема: <?= htmlspecialchars($s['theme']) ?></div>
        <div style="margin-top:8px;">
            <a href="/admin/subthemes.php?edit=<?= (int)$s['id'] ?>">✏️ Редактировать</a>
        </div>
    </div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>