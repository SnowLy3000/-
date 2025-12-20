<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    exit('Admins cannot take tests');
}

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testId = (int)$_POST['test_id'];
    $pass = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT * FROM tests WHERE id=? AND active=1
    ");
    $stmt->execute([$testId]);
    $test = $stmt->fetch();

    if (!$test || $test['access_password'] !== $pass) {
        $msg = 'Неверный пароль';
    } else {
        header("Location: /cabinet/take_test.php?test=$testId");
        exit;
    }
}

$tests = $pdo->query("
    SELECT id, title FROM tests WHERE active=1
")->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Тесты</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
</head>
<body>

<div class="card neon" style="max-width:600px;margin:60px auto;">
<h1>Доступные тесты</h1>

<?php if ($msg): ?><p><?= htmlspecialchars($msg) ?></p><?php endif; ?>

<?php foreach ($tests as $t): ?>
<form method="post" style="margin-bottom:12px;">
  <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
  <b><?= htmlspecialchars($t['title']) ?></b>
  <input name="password" placeholder="Пароль теста">
  <button class="btn">Начать</button>
</form>
<?php endforeach; ?>

<a href="/cabinet/index.php">← Назад</a>
</div>
</body>
</html>
