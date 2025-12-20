<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    header('Location: /admin/dashboard.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    exit('Пустой запрос');
}

$like = '%' . $q . '%';

// Темы
$themes = $pdo->prepare("
    SELECT id, title
    FROM themes
    WHERE title LIKE ? OR content LIKE ?
");
$themes->execute([$like, $like]);
$themes = $themes->fetchAll();

// Подтемы
$subthemes = $pdo->prepare("
    SELECT s.id, s.title, s.theme_id, t.title AS theme
    FROM subthemes s
    JOIN themes t ON t.id = s.theme_id
    WHERE s.title LIKE ? OR s.content LIKE ?
");
$subthemes->execute([$like, $like]);
$subthemes = $subthemes->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Поиск</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
</head>
<body>

<div class="card neon" style="max-width:900px;margin:40px auto;">
    <h1>🔍 Результаты поиска</h1>

    <p>Запрос: <b><?= htmlspecialchars($q) ?></b></p>

    <?php if (!$themes && !$subthemes): ?>
        <p>Ничего не найдено.</p>
    <?php endif; ?>

    <?php foreach ($themes as $t): ?>
        <div class="card neon">
            <b>📘 Тема:</b> <?= htmlspecialchars($t['title']) ?>
            <div style="margin-top:8px;">
                <a href="/cabinet/knowledge_view.php?theme=<?= (int)$t['id'] ?>">
                    Открыть
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($subthemes as $s): ?>
        <div class="card neon">
            <b>📄 Подтема:</b> <?= htmlspecialchars($s['title']) ?>
            <div style="opacity:.7;">Тема: <?= htmlspecialchars($s['theme']) ?></div>
            <div style="margin-top:8px;">
                <a href="/cabinet/knowledge_view.php?theme=<?= (int)$s['theme_id'] ?>">
                    Открыть тему
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="/cabinet/knowledge.php">← Назад к базе знаний</a>
</div>

</body>
</html>