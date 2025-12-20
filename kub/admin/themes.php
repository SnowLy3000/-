<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$message = null;

/**
 * СОЗДАНИЕ / ОБНОВЛЕНИЕ ТЕМЫ
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = $_POST['id'] ?? null;
    $title   = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if ($title === '') {
        $message = 'Название темы обязательно';
    } else {
        if ($id) {
            $pdo->prepare("
                UPDATE themes SET title = ?, content = ?
                WHERE id = ?
            ")->execute([$title, $content, (int)$id]);

            $message = 'Тема обновлена';
        } else {
            $pdo->prepare("
                INSERT INTO themes (title, content)
                VALUES (?, ?)
            ")->execute([$title, $content]);

            $message = 'Тема создана';
        }
    }
}

// Редактирование темы
$editTheme = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM themes WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTheme = $stmt->fetch();
}

// Все темы
$themes = $pdo->query("
    SELECT id, title
    FROM themes
    ORDER BY title
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Темы — база знаний</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/zufq95qlrqvk7gxmrsptp6rkuk4ivm1evmx1888qvqv33ami/tinymce/6/tinymce.min.js"></script>

<script>
tinymce.init({
    selector: 'textarea[name=content]',
    height: 420,
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
        <a href="/admin/themes.php"><b>Темы</b></a>
        <a href="/admin/logout.php">Выйти</a>
    </aside>

    <main class="admin-main">

        <h1>Темы — база знаний</h1>

        <?php if ($message): ?>
            <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- ФОРМА -->
        <div class="card neon">
            <h3><?= $editTheme ? 'Редактировать тему' : 'Создать тему' ?></h3>

            <form method="post">
                <?php if ($editTheme): ?>
                    <input type="hidden" name="id" value="<?= (int)$editTheme['id'] ?>">
                <?php endif; ?>

                <input
                    name="title"
                    placeholder="Название темы"
                    value="<?= htmlspecialchars($editTheme['title'] ?? '') ?>"
                >

                <textarea name="content"><?= htmlspecialchars($editTheme['content'] ?? '') ?></textarea>

                <button class="btn">
                    <?= $editTheme ? 'Сохранить' : 'Создать' ?>
                </button>
            </form>
        </div>

        <!-- СПИСОК ТЕМ -->
        <h3 style="margin-top:30px;">Существующие темы</h3>

        <?php foreach ($themes as $t): ?>
            <div class="card neon" style="margin-bottom:10px;">
                <b><?= htmlspecialchars($t['title']) ?></b>
                <div style="margin-top:8px;">
                    <a href="/admin/themes.php?edit=<?= (int)$t['id'] ?>">✏️ Редактировать</a>
                </div>
            </div>
        <?php endforeach; ?>

    </main>
</div>

</body>
</html>
