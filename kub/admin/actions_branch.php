<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

$actionId = (int)($_GET['action'] ?? 0);
$branchId = (int)($_GET['branch'] ?? 0);

if (!$actionId || !$branchId) {
    die('Некорректный запрос');
}

$stmt = $pdo->prepare("
    SELECT 
        u.fullname,
        aus.status,
        aus.updated_at
    FROM users u
    LEFT JOIN action_user_status aus
        ON aus.user_id = u.id AND aus.action_id = ?
    WHERE u.branch_id = ?
      AND u.role = 'employee'
    ORDER BY u.fullname
");
$stmt->execute([$actionId, $branchId]);
$users = $stmt->fetchAll();

$branch = $pdo->prepare("SELECT title FROM branches WHERE id=?");
$branch->execute([$branchId]);
$branch = $branch->fetchColumn();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($branch) ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="javascript:history.back()">← Назад</a>
</aside>

<main class="admin-main">

<h1>📍 <?= htmlspecialchars($branch) ?></h1>

<?php foreach ($users as $u): ?>
<div class="card neon">
    <b><?= htmlspecialchars($u['fullname']) ?></b>

    <?php if (!$u['status']): ?>
        <div style="color:#ff5555">❌ Не ознакомился</div>
    <?php elseif ($u['status'] === 'viewed'): ?>
        <div style="color:#ffd966">👀 Ознакомился</div>
    <?php elseif ($u['status'] === 'done'): ?>
        <div style="color:#7CFC98">✔ Выполнил</div>
    <?php endif; ?>

    <?php if ($u['updated_at']): ?>
        <div style="opacity:.7"><?= htmlspecialchars($u['updated_at']) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>