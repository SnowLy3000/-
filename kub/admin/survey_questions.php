<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$surveyId = (int)($_GET['survey'] ?? 0);
if (!$surveyId) {
    exit('Анкета не выбрана');
}

// Загружаем анкету
$stmt = $pdo->prepare("SELECT * FROM surveys WHERE id=?");
$stmt->execute([$surveyId]);
$survey = $stmt->fetch();

if (!$survey) {
    exit('Анкета не найдена');
}

$message = null;

/**
 * ДОБАВЛЕНИЕ ВОПРОСА
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_question') {
    $question = trim($_POST['question'] ?? '');

    if ($question === '') {
        $message = 'Текст вопроса обязателен';
    } else {
        $pdo->prepare("
            INSERT INTO survey_questions (survey_id, question)
            VALUES (?, ?)
        ")->execute([$surveyId, $question]);

        $message = 'Вопрос добавлен';
    }
}

// Список вопросов анкеты
$questions = $pdo->prepare("
    SELECT * FROM survey_questions
    WHERE survey_id = ?
    ORDER BY id ASC
");
$questions->execute([$surveyId]);
$questions = $questions->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Вопросы анкеты</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/surveys.php">← Анкеты</a>
    <a href="/admin/dashboard.php">Dashboard</a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>Анкета: <?= htmlspecialchars($survey['title']) ?></h1>

<?php if ($message): ?>
    <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- ДОБАВЛЕНИЕ ВОПРОСА -->
<div class="card neon">
    <h3>Добавить вопрос</h3>

    <form method="post">
        <input type="hidden" name="action" value="add_question">
        <textarea name="question" placeholder="Введите текст вопроса"></textarea>
        <button class="btn">Добавить</button>
    </form>
</div>

<!-- СПИСОК ВОПРОСОВ -->
<h3 style="margin-top:30px;">Вопросы анкеты</h3>

<?php if (!$questions): ?>
    <p>Вопросов пока нет.</p>
<?php endif; ?>

<?php foreach ($questions as $i => $q): ?>
    <div class="card neon" style="margin-bottom:10px;">
        <b><?= ($i + 1) ?>.</b> <?= htmlspecialchars($q['question']) ?>
    </div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>
