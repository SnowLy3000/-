<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    exit('Admins cannot fill surveys');
}

$userId = (int)$_SESSION['user']['id'];

// Берём ТОЛЬКО те анкеты, на которые пользователь ЕЩЁ НЕ отвечал
$surveys = $pdo->prepare("
    SELECT s.id, s.title
    FROM surveys s
    WHERE s.active = 1
      AND NOT EXISTS (
        SELECT 1
        FROM survey_answers sa
        WHERE sa.survey_id = s.id
          AND sa.user_id = ?
      )
    ORDER BY s.created_at DESC
");
$surveys->execute([$userId]);
$surveys = $surveys->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Анкеты</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<style>
.important {
    color: #ff5555;
    font-weight: bold;
    animation: blink 1.5s infinite;
}
@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: .4; }
    100% { opacity: 1; }
}
</style>
</head>
<body>

<div class="card neon" style="max-width:700px;margin:60px auto;">
    <h1>Анкеты</h1>

    <?php if (!$surveys): ?>
        <p style="color:#9ff;">🎉 У вас нет новых анкет</p>
    <?php endif; ?>

    <?php foreach ($surveys as $s): ?>
        <div class="card neon" style="margin-bottom:12px;">
            <b><?= htmlspecialchars($s['title']) ?></b>
            <span class="important"> (ВАЖНО)</span>

            <div style="margin-top:10px;">
                <a href="/cabinet/survey_fill.php?survey=<?= (int)$s['id'] ?>" class="btn">
                    Ответить
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="/cabinet/index.php">← Назад в кабинет</a>
</div>

</body>
</html>
