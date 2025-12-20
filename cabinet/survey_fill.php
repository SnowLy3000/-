<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    exit('Admins cannot fill surveys');
}

$userId   = (int)$_SESSION['user']['id'];
$surveyId = (int)($_GET['survey'] ?? 0);

if (!$surveyId) {
    exit('Анкета не выбрана');
}

// Анкета
$stmt = $pdo->prepare("SELECT * FROM surveys WHERE id=? AND active=1");
$stmt->execute([$surveyId]);
$survey = $stmt->fetch();

if (!$survey) {
    exit('Анкета не найдена');
}

// Вопросы
$q = $pdo->prepare("
    SELECT * FROM survey_questions
    WHERE survey_id = ?
    ORDER BY id ASC
");
$q->execute([$surveyId]);
$questions = $q->fetchAll();

if (!$questions) {
    exit('В анкете нет вопросов');
}

// Проверка — отвечал ли уже
$check = $pdo->prepare("
    SELECT 1 FROM survey_answers
    WHERE survey_id = ? AND user_id = ?
    LIMIT 1
");
$check->execute([$surveyId, $userId]);
if ($check->fetch()) {
    exit('Вы уже отвечали на эту анкету');
}

// Сохранение
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $q) {
        $answer = trim($_POST['q'.$q['id']] ?? '');
        if ($answer === '') {
            exit('Ответьте на все вопросы');
        }

        $pdo->prepare("
            INSERT INTO survey_answers (survey_id, question_id, user_id, answer)
            VALUES (?, ?, ?, ?)
        ")->execute([$surveyId, $q['id'], $userId, $answer]);
    }

    // Страница спасибо + редирект
    echo "
    <!doctype html>
    <html lang='ru'>
    <head>
        <meta charset='utf-8'>
        <meta http-equiv='refresh' content='3;url=/cabinet/index.php'>
        <link rel='stylesheet' href='/assets/css/base.css'>
        <link rel='stylesheet' href='/assets/css/neon.css'>
    </head>
    <body>
        <div class='card neon' style='max-width:600px;margin:80px auto;text-align:center;'>
            <h2>✅ Спасибо!</h2>
            <p>Анкета успешно отправлена.</p>
            <p style='opacity:.7;'>Вы будете перенаправлены в кабинет через 3 секунды…</p>
            <a class='btn' href='/cabinet/index.php'>Вернуться сейчас</a>
        </div>
    </body>
    </html>
    ";
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($survey['title']) ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
</head>
<body>

<div class="card neon" style="max-width:800px;margin:40px auto;">
<h1><?= htmlspecialchars($survey['title']) ?></h1>

<form method="post">
<?php foreach ($questions as $i => $q): ?>
    <div class="card neon" style="margin-bottom:15px;">
        <b><?= ($i+1) ?>. <?= htmlspecialchars($q['question']) ?></b>
        <textarea name="q<?= (int)$q['id'] ?>" placeholder="Ваш ответ"></textarea>
    </div>
<?php endforeach; ?>

<button class="btn">Отправить ответы</button>
</form>

</div>
</body>
</html>
