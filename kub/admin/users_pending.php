<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('USER_APPROVE');

/**
 * ----------------------------
 * ПОДТВЕРЖДЕНИЕ / ОТКЛОНЕНИЕ
 * ----------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';

    if ($id > 0) {
        if ($do === 'approve') {
            $pdo->prepare("
                UPDATE users
                SET status='active'
                WHERE id=? AND status='pending'
            ")->execute([$id]);
        }

        if ($do === 'reject') {
            $pdo->prepare("
                DELETE FROM users
                WHERE id=? AND status='pending'
            ")->execute([$id]);
        }
    }

    header('Location: users_pending.php');
    exit;
}

/**
 * ----------------------------
 * СПИСОК ОЖИДАЮЩИХ
 * ----------------------------
 */
$users = $pdo->query("
    SELECT id, fullname, phone, birthdate, created_at
    FROM users
    WHERE status='pending'
    ORDER BY created_at ASC
")->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Подтверждение сотрудников</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <hr style="margin:15px 0;opacity:.2">
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>👥 Ожидают подтверждения</h1>

<?php if (!$users): ?>
    <p>Нет заявок.</p>
<?php endif; ?>

<?php foreach ($users as $u): ?>
<div class="card neon">
    <b><?= htmlspecialchars($u['fullname']) ?></b><br>
    📞 <?= htmlspecialchars($u['phone']) ?><br>
    🎂 <?= htmlspecialchars($u['birthdate']) ?><br>
    🕒 <?= htmlspecialchars($u['created_at']) ?>

    <div style="margin-top:10px;display:flex;gap:10px;">
        <form method="post">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="do" value="approve">
            <button class="btn">✅ Подтвердить</button>
        </form>

        <form method="post" onsubmit="return confirm('Удалить заявку?')">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="do" value="reject">
            <button class="btn btn-danger">❌ Отклонить</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>