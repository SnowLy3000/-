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

// Анкета
$stmt = $pdo->prepare("
    SELECT s.*, 
           t.title AS theme_title, 
           st.title AS subtheme_title
    FROM surveys s
    LEFT JOIN themes t ON t.id = s.theme_id
    LEFT JOIN subthemes st ON st.id = s.subtheme_id
    WHERE s.id = ?
");
$stmt->execute([$surveyId]);
$survey = $stmt->fetch();

if (!$survey) {
    exit('Анкета не найдена');
}

// Вопросы анкеты
$q = $pdo->prepare("
    SELECT * 
    FROM survey_questions
    WHERE survey_id = ?
    ORDER BY id ASC
");
$q->execute([$surveyId]);
$questions = $q->fetchAll();

// Все сотрудники
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'employee' AND status = 'active'
    ORDER BY fullname
")->fetchAll();

// Ответы
$answersStmt = $pdo->prepare("
    SELECT *
    FROM survey_answers
    WHERE survey_id = ?
");
$answersStmt->execute([$surveyId]);
$answersRaw = $answersStmt->fetchAll();

// Индексация ответов: [question_id][user_id] = answer
$answers = [];
foreach ($answersRaw as $a) {
    $answers[$a['question_id']][$a['user_id']] = $a['answer'];
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Результаты анкеты</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.not-answered {
    color: #ff5555;
    font-weight: bold;
}
.answered {
    color: #9ff;
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/surveys.php">← Анкеты</a>
    <a href="/admin/dashboard.php">Dashboard</a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1><?= htmlspecialchars($survey['title']) ?></h1>

<?php if ($survey['theme_title']): ?>
    <p>Тема: <b><?= htmlspecialchars($survey['theme_title']) ?></b></p>
<?php endif; ?>

<?php if ($survey['subtheme_title']): ?>
    <p>Подтема: <b><?= htmlspecialchars($survey['subtheme_title']) ?></b></p>
<?php endif; ?>

<hr style="opacity:.2;margin:20px 0;">

<?php foreach ($questions as $i => $q): ?>
    <div class="card neon" style="margin-bottom:20px;">
        <h3><?= ($i+1) ?>. <?= htmlspecialchars($q['question']) ?></h3>

        <?php foreach ($users as $u): ?>
            <?php if (isset($answers[$q['id']][$u['id']])): ?>
                <div class="answered">
                    <?= htmlspecialchars($u['fullname']) ?>:
                    <div style="margin-left:15px;">
                        <?= nl2br(htmlspecialchars($answers[$q['id']][$u['id']])) ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="not-answered">
                    <?= htmlspecialchars($u['fullname']) ?> — НЕ ОТВЕТИЛ
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>
