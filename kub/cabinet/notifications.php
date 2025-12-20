<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/notifications.php';

require_login();

$userId = $_SESSION['user']['id'];

if (isset($_GET['read'])) {
    notif_mark_read($pdo, $userId, (int)$_GET['read']);
    header("Location: /cabinet/notifications.php");
    exit;
}

$items = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id=?
    ORDER BY is_read ASC, created_at DESC
    LIMIT 200
");
$items->execute([$userId]);
$items = $items->fetchAll();

$unread = notif_unread_count($pdo, $userId);

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Уведомления</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
</head>
<body>

<h1>🔔 Уведомления <?= $unread ? "($unread)" : "" ?></h1>

<?php if (!$items): ?>
  <p>Уведомлений нет.</p>
<?php endif; ?>

<?php foreach ($items as $n): ?>
  <div class="card neon" style="<?= $n['is_read'] ? 'opacity:.75' : 'border:2px solid #ff3333' ?>">
    <b><?= htmlspecialchars($n['title']) ?></b>
    <?php if (!empty($n['body'])): ?>
      <div style="margin-top:6px;"><?= nl2br(htmlspecialchars($n['body'])) ?></div>
    <?php endif; ?>
    <div style="opacity:.7;margin-top:6px;">
      <?= htmlspecialchars($n['created_at']) ?>
      <?= $n['is_read'] ? '• прочитано' : '• новое' ?>
    </div>

    <div style="margin-top:10px;">
      <?php if (!empty($n['link'])): ?>
        <a class="btn" href="<?= htmlspecialchars($n['link']) ?>">Открыть</a>
      <?php endif; ?>
      <?php if (!$n['is_read']): ?>
        <a class="btn" href="/cabinet/notifications.php?read=<?= (int)$n['id'] ?>">Отметить прочитанным</a>
      <?php endif; ?>
      <a class="btn" href="/cabinet/index.php">Назад</a>
    </div>
  </div>
<?php endforeach; ?>

</body>
</html>