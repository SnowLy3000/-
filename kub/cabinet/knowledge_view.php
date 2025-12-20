<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    header('Location: /admin/dashboard.php');
    exit;
}

$userId  = (int)$_SESSION['user']['id'];
$themeId = (int)($_GET['theme'] ?? 0);

if (!$themeId) {
    exit('Тема не выбрана');
}

// Тема
$stmt = $pdo->prepare("SELECT * FROM themes WHERE id=?");
$stmt->execute([$themeId]);
$theme = $stmt->fetch();

if (!$theme) {
    exit('Тема не найдена');
}

// ФИКСАЦИЯ «ПРОЧИТАНО» ТЕМЫ
$pdo->prepare("
    INSERT IGNORE INTO knowledge_views (user_id, theme_id)
    VALUES (?, ?)
")->execute([$userId, $themeId]);

// Подтемы
$subthemes = $pdo->prepare("
    SELECT s.*,
           IF(kv.id IS NULL, 0, 1) AS viewed
    FROM subthemes s
    LEFT JOIN knowledge_views kv
        ON kv.subtheme_id = s.id
       AND kv.user_id = ?
    WHERE s.theme_id = ?
    ORDER BY s.title
");
$subthemes->execute([$userId, $themeId]);
$subthemes = $subthemes->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($theme['title']) ?></title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.content { line-height:1.7; }
.read { color:#9ff; font-weight:bold; }
</style>
</head>
<body>

<div class="card neon" style="max-width:1000px;margin:40px auto;">

<h1><?= htmlspecialchars($theme['title']) ?></h1>

<?php if (!empty($theme['content'])): ?>
    <div class="content card neon">
        <?= $theme['content'] ?>
    </div>
<?php endif; ?>

<?php foreach ($subthemes as $s): ?>
    <div class="card neon" style="margin-top:15px;">
        <h3>
            <?= htmlspecialchars($s['title']) ?>
            <?php if ($s['viewed']): ?>
                <span class="read">✔️</span>
            <?php endif; ?>
        </h3>

        <?php
            // ФИКСАЦИЯ «ПРОЧИТАНО» ПОДТЕМЫ
            if (!$s['viewed']) {
                $pdo->prepare("
                    INSERT IGNORE INTO knowledge_views (user_id, subtheme_id)
                    VALUES (?, ?)
                ")->execute([$userId, $s['id']]);
            }
        ?>

        <?php if (!empty($s['content'])): ?>
            <div class="content">
                <?= $s['content'] ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<a href="/cabinet/knowledge.php">← К списку тем</a>

</div>

</body>
</html>