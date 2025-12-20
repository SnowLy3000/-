<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$message = null;

/**
 * СОЗДАНИЕ АНКЕТЫ
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_survey') {
    $title = trim($_POST['title'] ?? '');
    $themeId = $_POST['theme_id'] !== '' ? (int)$_POST['theme_id'] : null;
    $subthemeId = $_POST['subtheme_id'] !== '' ? (int)$_POST['subtheme_id'] : null;

    if ($title === '') {
        $message = 'Название анкеты обязательно';
    } else {
        $pdo->prepare("
            INSERT INTO surveys (title, theme_id, subtheme_id)
            VALUES (?, ?, ?)
        ")->execute([$title, $themeId, $subthemeId]);

        $message = 'Анкета создана';
    }
}

/**
 * ДАННЫЕ
 */

// Темы
$themes = $pdo->query("
    SELECT id, title
    FROM themes
    ORDER BY title
")->fetchAll();

// Подтемы
$subthemes = $pdo->query("
    SELECT s.id, s.title, t.title AS theme
    FROM subthemes s
    JOIN themes t ON t.id = s.theme_id
    ORDER BY t.title, s.title
")->fetchAll();

// Анкеты
$surveys = $pdo->query("
    SELECT s.*,
           t.title AS theme_title,
           st.title AS subtheme_title
    FROM surveys s
    LEFT JOIN themes t ON t.id = s.theme_id
    LEFT JOIN subthemes st ON st.id = s.subtheme_id
    ORDER BY s.id DESC
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Анкеты</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

    <aside class="admin-menu neon">
        <a href="/admin/dashboard.php">← Dashboard</a>
        <a href="/admin/surveys.php"><b>Анкеты</b></a>
        <a href="/admin/logout.php">Выйти</a>
    </aside>

    <main class="admin-main">

        <h1>Анкеты (открытые вопросы)</h1>

        <?php if ($message): ?>
            <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- СОЗДАНИЕ АНКЕТЫ -->
        <div class="card neon">
            <h3>Создать анкету</h3>

            <form method="post">
                <input type="hidden" name="action" value="add_survey">

                <input name="title" placeholder="Название анкеты">

                <label>
                    Тема (необязательно):
                    <select name="theme_id">
                        <option value="">— без темы —</option>
                        <?php foreach ($themes as $t): ?>
                            <option value="<?= (int)$t['id'] ?>">
                                <?= htmlspecialchars($t['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Подтема (необязательно):
                    <select name="subtheme_id">
                        <option value="">— без подтемы —</option>
                        <?php foreach ($subthemes as $s): ?>
                            <option value="<?= (int)$s['id'] ?>">
                                <?= htmlspecialchars($s['theme']) ?> → <?= htmlspecialchars($s['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button class="btn">Создать</button>
            </form>
        </div>

        <!-- СПИСОК АНКЕТ -->
        <h3 style="margin-top:30px;">Существующие анкеты</h3>

        <?php if (!$surveys): ?>
            <p>Анкет пока нет.</p>
        <?php endif; ?>

        <?php foreach ($surveys as $s): ?>
            <div class="card neon" style="margin-bottom:12px;">
                <b><?= htmlspecialchars($s['title']) ?></b>

                <div>
                    <?= $s['theme_title']
                        ? 'Тема: '.htmlspecialchars($s['theme_title'])
                        : 'Без темы' ?>
                </div>

                <?php if ($s['subtheme_title']): ?>
                    <div>
                        Подтема: <?= htmlspecialchars($s['subtheme_title']) ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:10px;display:flex;gap:20px;flex-wrap:wrap;">
                    <a href="/admin/survey_questions.php?survey=<?= (int)$s['id'] ?>">
                        ➕ Вопросы
                    </a>

                    <a href="/admin/survey_results.php?survey=<?= (int)$s['id'] ?>">
                        📊 Результаты
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

    </main>
</div>

</body>
</html>
