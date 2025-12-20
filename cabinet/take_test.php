<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$testId = (int)($_GET['test'] ?? 0);
$userId = (int)$_SESSION['user']['id'];

$test = $pdo->prepare("SELECT * FROM tests WHERE id=? AND active=1");
$test->execute([$testId]);
$test = $test->fetch();
if (!$test) exit('Test not found');

// вопросы
$q = $pdo->prepare("
    SELECT q.*
    FROM questions q
    JOIN test_questions tq ON tq.question_id = q.id
    WHERE tq.test_id = ?
");
$q->execute([$testId]);
$questions = $q->fetchAll();

$score = 0;
$hintsUsed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($questions as $qu) {
        $ans = (int)($_POST['q'.$qu['id']] ?? 0);
        if ($ans === (int)$qu['correct']) {
            $score += (int)$qu['score'];
        }
    }

    $passed = $score >= (int)$test['pass_score'];

    $pdo->prepare("
        INSERT INTO results
        (user_id, test_id, score, passed, hints_used, started_at, finished_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ")->execute([$userId, $testId, $score, $passed, $hintsUsed]);

    echo "<h2>Результат: ".($passed?'ПРОЙДЕНО':'НЕ ПРОЙДЕНО')."</h2>";
    echo "<p>Баллы: $score</p>";
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($test['title']) ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
</head>
<body>

<div class="card neon" style="max-width:800px;margin:40px auto;">
<h1><?= htmlspecialchars($test['title']) ?></h1>

<form method="post">
<?php foreach ($questions as $q): ?>
<div class="card neon" style="margin-bottom:12px;">
  <b><?= htmlspecialchars($q['question']) ?></b>
  <?php for ($i=1;$i<=4;$i++): ?>
    <label style="display:block;">
      <input type="radio" name="q<?= $q['id'] ?>" value="<?= $i ?>">
      <?= htmlspecialchars($q['a'.$i]) ?>
    </label>
  <?php endfor; ?>

  <?php if ($test['allow_hints'] && $q['hint_text']): ?>
    <div style="opacity:.7">💡 <?= htmlspecialchars($q['hint_text']) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<button class="btn">Завершить тест</button>
</form>

</div>
</body>
</html>
