<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$testId = (int)($_GET['test'] ?? 0);
if (!$testId) {
    exit('Test not selected');
}

// тест
$test = $pdo->prepare("SELECT * FROM tests WHERE id=?");
$test->execute([$testId]);
$test = $test->fetch();
if (!$test) exit('Test not found');

// доступные вопросы
if ($test['type'] === 'simple') {
    $questions = $pdo->query("SELECT id, question FROM questions WHERE subtheme_id IS NULL")->fetchAll();
} else {
    $questions = $pdo->query("
        SELECT q.id, q.question
        FROM questions q
        JOIN subthemes s ON s.id = q.subtheme_id
    ")->fetchAll();
}

// сохранение связей
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("DELETE FROM test_questions WHERE test_id=?")->execute([$testId]);

    foreach ($_POST['questions'] ?? [] as $qid) {
        $pdo->prepare("
            INSERT INTO test_questions (test_id, question_id)
            VALUES (?, ?)
        ")->execute([$testId, (int)$qid]);
    }
}

$linked = $pdo->prepare("
    SELECT question_id FROM test_questions WHERE test_id=?
");
$linked->execute([$testId]);
$linkedIds = array_column($linked->fetchAll(), 'question_id');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Вопросы теста</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">
<aside class="admin-menu neon">
  <a href="/admin/tests.php">← Тесты</a>
  <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">
<h1><?= htmlspecialchars($test['title']) ?></h1>

<form method="post">
<?php foreach ($questions as $q): ?>
<label style="display:block;margin:6px 0;">
  <input type="checkbox" name="questions[]" value="<?= $q['id'] ?>"
    <?= in_array($q['id'], $linkedIds) ? 'checked' : '' ?>>
  <?= htmlspecialchars($q['question']) ?>
</label>
<?php endforeach; ?>

<button class="btn" style="margin-top:10px;">Сохранить</button>
</form>

</main>
</div>
</body>
</html>
