<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/notifications.php';

require_login();

$userId   = $_SESSION['user']['id'];
$branchId = $_SESSION['user']['branch_id'] ?? null;

/**
 * ============================
 * ОБРАБОТКА ДЕЙСТВИЙ СОТРУДНИКА
 * ============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $actionId = (int)($_POST['action_id'] ?? 0);
    $status   = $_POST['status'] ?? '';

    if ($actionId && in_array($status, ['viewed','done'], true)) {

        // фиксируем статус
        $stmt = $pdo->prepare("
            INSERT INTO action_user_status (action_id, user_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->execute([$actionId, $userId, $status]);

        // гасим уведомление при любом действии
        notif_mark_read_by_entity(
            $pdo,
            $userId,
            'action',
            $actionId
        );
    }

    header('Location: actions.php');
    exit;
}

/**
 * ============================
 * СПИСОК АКЦИЙ ДЛЯ СОТРУДНИКА
 * ============================
 */
$actions = [];

if ($branchId) {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            aus.status
        FROM actions a
        JOIN action_branches ab ON ab.action_id = a.id
        LEFT JOIN action_user_status aus
            ON aus.action_id = a.id
           AND aus.user_id = ?
        WHERE ab.branch_id = ?
          AND a.deleted_at IS NULL
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$userId, $branchId]);
    $actions = $stmt->fetchAll();
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Акции и инструкции</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.action-card {
    padding:16px;
    margin-bottom:14px;
    border-radius:8px;
}
.action-important {
    border:2px solid #ff4444;
}
.action-required {
    border:2px solid #ffcc00;
}
.badge {
    display:inline-block;
    padding:3px 8px;
    font-size:12px;
    border-radius:6px;
    margin-right:6px;
}
.badge-important { background:#ff4444; }
.badge-required  { background:#ffcc00; color:#000; }
.badge-done      { background:#44ff99; color:#000; }
.badge-viewed    { background:#66ccff; color:#000; }
</style>

</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/cabinet/index.php">← Кабинет</a>
    <a href="/cabinet/actions.php"><b>📢 Акции и инструкции</b></a>
    <a href="/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📢 Акции и инструкции</h1>

<?php if (!$actions): ?>
    <p>Нет активных акций.</p>
<?php endif; ?>

<?php foreach ($actions as $a): ?>

<?php
$cls = 'action-card neon';
if ($a['is_important']) $cls .= ' action-important';
if ($a['is_required'])  $cls .= ' action-required';
?>

<div class="<?= $cls ?>">

    <h3><?= htmlspecialchars($a['title']) ?></h3>

    <div style="margin-bottom:8px;">
        <?php if ($a['is_important']): ?>
            <span class="badge badge-important">Важно</span>
        <?php endif; ?>
        <?php if ($a['is_required']): ?>
            <span class="badge badge-required">Обязательно</span>
        <?php endif; ?>
        <?php if ($a['status'] === 'done'): ?>
            <span class="badge badge-done">Выполнено</span>
        <?php elseif ($a['status'] === 'viewed'): ?>
            <span class="badge badge-viewed">Ознакомился</span>
        <?php endif; ?>
    </div>

    <?php if ($a['due_at']): ?>
        <div style="opacity:.7;margin-bottom:8px;">
            Выполнить до: <?= htmlspecialchars($a['due_at']) ?>
        </div>
    <?php endif; ?>

    <div style="margin-bottom:12px;">
        <?= $a['content'] ?>
    </div>

    <form method="post" style="display:inline">
        <input type="hidden" name="action_id" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="status" value="viewed">
        <button class="btn">👀 Ознакомился</button>
    </form>

    <form method="post" style="display:inline">
        <input type="hidden" name="action_id" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="status" value="done">
        <button class="btn">✔ Выполнил</button>
    </form>

</div>

<?php endforeach; ?>

</main>
</div>

</body>
</html>
