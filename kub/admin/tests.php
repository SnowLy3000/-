<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$message = null;

/**
 * СОЗДАНИЕ ТЕСТА
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_test') {

    $title = trim($_POST['title'] ?? '');
    $type  = $_POST['type'] ?? 'simple';

    $timeLimit    = $_POST['time_limit'] !== '' ? (int)$_POST['time_limit'] : null;
    $attemptLimit = $_POST['attempt_limit'] !== '' ? (int)$_POST['attempt_limit'] : null;
    $passScore    = (int)($_POST['pass_score'] ?? 0);

    $password = trim($_POST['access_password'] ?? '');

    $allowHints           = isset($_POST['allow_hints']) ? 1 : 0;
    $showCorrectOnError   = isset($_POST['show_correct_on_error']) ? 1 : 0;
    $showCorrectOnFinish  = isset($_POST['show_correct_on_finish']) ? 1 : 0;

    if ($title === '' || $password === '') {
        $message = 'Название и пароль обязательны';
    } else {
        $pdo->prepare("
            INSERT INTO tests
            (title, type, time_limit, attempt_limit, pass_score, access_password,
             allow_hints, show_correct_on_error, show_correct_on_finish)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $title,
            $type,
            $timeLimit,
            $attemptLimit,
            $passScore,
            $password,
            $allowHints,
            $showCorrectOnError,
            $showCorrectOnFinish
        ]);

        $message = 'Тест создан';
    }
}

// Загрузка тестов
$tests = $pdo->query("
    SELECT *
    FROM tests
    ORDER BY id DESC
")->fetchAll();

// Вопросы без темы
$freeQuestions = $pdo->query("
    SELECT id, question FROM questions WHERE subtheme_id IS NULL
")->fetchAll();

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
    <title>Тесты / экзамены</title>
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
        <a href="/admin/tests.php">Тесты</a>
        <a href="/admin/test_questions.php?test=<?= $t['id'] ?>">Вопросы</a>
        <a href="/admin/logout.php">Выйти</a>
    </aside>

    <main class="admin-main">

        <h1>Тесты / экзамены</h1>

        <?php if ($message): ?>
            <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- СОЗДАНИЕ ТЕСТА -->
        <div class="card neon">
            <h3>Создать тест</h3>

            <form method="post">
                <input type="hidden" name="action" value="add_test">

                <input name="title" placeholder="Название теста">

                <label>
                    Тип:
                    <select name="type">
                        <option value="simple">Простой (без тем)</option>
                        <option value="themed">По темам</option>
                    </select>
                </label>

                <input name="access_password" placeholder="Пароль доступа">

                <input name="time_limit" type="number" placeholder="Время (минуты)">
                <input name="attempt_limit" type="number" placeholder="Лимит попыток">
                <input name="pass_score" type="number" placeholder="Проходной балл">

                <label><input type="checkbox" name="allow_hints" checked> Разрешить подсказки</label>
                <label><input type="checkbox" name="show_correct_on_error"> Показывать ответ при ошибке</label>
                <label><input type="checkbox" name="show_correct_on_finish" checked> Показывать ответы в конце</label>

                <button class="btn">Создать</button>
            </form>
        </div>

        <!-- СПИСОК ТЕСТОВ -->
        <h3 style="margin-top:30px;">Существующие тесты</h3>

        <?php foreach ($tests as $t): ?>
            <div class="card neon" style="margin-bottom:10px;">
                <b><?= htmlspecialchars($t['title']) ?></b>
                <div>Тип: <?= htmlspecialchars($t['type']) ?></div>
                <div>Пароль: <code><?= htmlspecialchars($t['access_password']) ?></code></div>
                <div>Активен: <?= $t['active'] ? 'Да' : 'Нет' ?></div>
            </div>
        <?php endforeach; ?>

    </main>
</div>

</body>
</html>
