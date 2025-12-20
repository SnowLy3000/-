<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/delete_request.php';

require_admin();
require_permission('USER_APPROVE'); // просмотр сотрудников

$message = null;

// =======================
// ЗАПРОС НА УДАЛЕНИЕ СОТРУДНИКА
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    require_permission('USER_DELETE_REQUEST');

    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $ok = createDeleteRequest(
            $pdo,
            'user',
            $userId,
            $_SESSION['user']['id']
        );

        $message = $ok
            ? 'Запрос на удаление сотрудника отправлен владельцу'
            : 'Запрос на удаление уже существует';
    }
}

// =======================
// СПИСОК СОТРУДНИКОВ
// =======================
$users = $pdo->query("
    SELECT u.*,
           b.title AS branch_title,
           (
             SELECT COUNT(*)
             FROM delete_requests dr
             WHERE dr.entity_type='user'
               AND dr.entity_id=u.id
               AND dr.status='pending'
           ) AS has_delete_request
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    WHERE u.role = 'employee'
    ORDER BY u.fullname
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Сотрудники</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/users.php"><b>👥 Сотрудники</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>👥 Сотрудники</h1>

<?php if ($message): ?>
    <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if (!$users): ?>
    <p>Сотрудников нет.</p>
<?php endif; ?>

<?php foreach ($users as $u): ?>
    <div class="card neon">

        <b><?= htmlspecialchars($u['fullname']) ?></b>

        <div><?= htmlspecialchars($u['phone'] ?? '') ?></div>
        <div style="opacity:.8">
            Филиал:
            <?= htmlspecialchars($u['branch_title'] ?? '—') ?>
        </div>

        <?php if ($u['has_delete_request']): ?>
            <div style="color:#ff7777;margin-top:6px;">
                ⏳ Ожидает подтверждения владельца
            </div>
        <?php elseif (user_has('USER_DELETE_REQUEST')): ?>
            <form method="post"
                  onsubmit="return confirm('Вы уверены, что хотите отправить запрос на удаление сотрудника?');"
                  style="margin-top:10px;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button class="btn">🗑 Удалить</button>
            </form>
        <?php endif; ?>

    </div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>