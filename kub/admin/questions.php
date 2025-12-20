<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$subthemeId = isset($_GET['subtheme']) ? (int)$_GET['subtheme'] : null;
$message = null;

/**
 * СОЗДАНИЕ ВОПРОСА
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_question') {

    $question = trim($_POST['question'] ?? '');

    $a1 = trim($_POST['a1'] ?? '');
    $a2 = trim($_POST['a2'] ?? '');
    $a3 = trim($_POST['a3'] ?? '');
    $a4 = trim($_POST['a4'] ?? '');

    $correct = (int)($_POST['correct'] ?? 0);

    $hintText = trim($_POST['hint_text'] ?? '');
    $hintLink = trim($_POST['hint_link'] ?? '');

    if (
        $question === '' ||
        $a1 === '' || $a2 === '' || $a3 === '' || $a4 === '' ||
        !in_array($correct, [1,2,3,4], true)
    ) {
        $message = 'Заполните вопрос, ответы и выберите правильный';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO questions
            (subtheme_id, question, a1, a2, a3, a4, correct, hint_text, hint_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $subthemeId ?: null,
            $question,
            $a1, $a2, $a3, $a4,
            $correct,
            $hintText ?: null,
            $hintLink ?: null
        ]);

        $message = 'Вопрос добавлен';
    }
}

/**
 * ЗАГРУЗКА ПОДТЕМ И ВОПРОСОВ
 */
$subthemes = $pdo->query("
    SELECT s.id, s.title, t.title AS theme_title
    FROM subthemes s
    JOIN themes t ON t.id = s.theme_id
    ORDER BY t.title, s.title
")->fetchAll();

if ($subthemeId) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM questions
        WHERE subtheme_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$subthemeId]);
} else {
    $stmt = $pdo->query("
        SELECT *
        FROM questions
        WHERE subtheme_id IS NULL
        ORDER BY id DESC
    ");
}

$questions = $stmt->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вопросы</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/neon.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

    <aside class="admin-menu neon">
        <a href="/admin/dashboard.php">← Dashboard</a>
        <a href="/admin/themes.php">Темы</a>
        <a href="/admin/questions.php">Вопросы</a>
        <a href="/admin/logout.php">Выйти</a>
    </aside>

    <main class="admin-main">

        <h1>Вопросы</h1>

        <?php if ($message): ?>
            <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- ВЫБОР ПОДТЕМЫ -->
        <div class="card neon">
            <form method="get">
                <label><b>Подтема (или без темы):</b></label>

                <select name="subtheme">
                    <option value="">— Без темы (простой тест) —</option>
                    <?php foreach ($subthemes as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                            <?= $subthemeId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['theme_title']) ?> → <?= htmlspecialchars($s['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn" style="margin-top:10px;">Выбрать</button>
            </form>
        </div>

        <!-- ДОБАВЛЕНИЕ ВОПРОСА -->
        <div class="card neon" style="margin-top:20px;">
            <h3>Добавить вопрос</h3>

            <form method="post">
                <input type="hidden" name="action" value="add_question">

                <textarea name="question" placeholder="Текст вопроса"></textarea>

                <label>
                    <input type="radio" name="correct" value="1">
                    <input name="a1" placeholder="Ответ 1">
                </label>

                <label>
                    <input type="radio" name="correct" value="2">
                    <input name="a2" placeholder="Ответ 2">
                </label>

                <label>
                    <input type="radio" name="correct" value="3">
                    <input name="a3" placeholder="Ответ 3">
                </label>

                <label>
                    <input type="radio" name="correct" value="4">
                    <input name="a4" placeholder="Ответ 4">
                </label>

                <input name="hint_text" placeholder="Подсказка (где искать ответ)">
                <input name="hint_link" placeholder="Ссылка (необязательно)">

                <button class="btn" style="margin-top:10px;">Сохранить</button>
            </form>
        </div>

        <!-- СПИСОК ВОПРОСОВ -->
        <h3 style="margin-top:30px;">Список вопросов</h3>

        <?php if (!$questions): ?>
            <p>Вопросов пока нет.</p>
        <?php endif; ?>

        <?php foreach ($questions as $q): ?>
            <div class="card neon" style="margin-bottom:10px;">
                <b><?= htmlspecialchars($q['question']) ?></b>
                <ol>
                    <li><?= htmlspecialchars($q['a1']) ?></li>
                    <li><?= htmlspecialchars($q['a2']) ?></li>
                    <li><?= htmlspecialchars($q['a3']) ?></li>
                    <li><?= htmlspecialchars($q['a4']) ?></li>
                </ol>
                <div>✅ Правильный: Ответ <?= (int)$q['correct'] ?></div>

                <?php if ($q['hint_text']): ?>
                    <div>💡 Подсказка: <?= htmlspecialchars($q['hint_text']) ?></div>
                <?php endif; ?>

                <?php if ($q['hint_link']): ?>
                    <div>🔗 <a href="<?= htmlspecialchars($q['hint_link']) ?>" target="_blank">Ссылка</a></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    </main>
</div>

</body>
</html>
