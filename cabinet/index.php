<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$user = $_SESSION['user'] ?? [];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Кабинет</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<style>
.wrap{max-width:900px;margin:30px auto;padding:0 14px}
.cardx{
    padding:18px;
    border-radius:18px;
    background:rgba(0,0,0,.25);
    border:1px solid rgba(255,255,255,.10);
}
.small{opacity:.75}
</style>
</head>
<body>

<div class="wrap">
    <div class="card neon cardx">
        <h2 style="margin-top:0;">👤 Кабинет</h2>

        <div><b>Пользователь:</b> <?= htmlspecialchars((string)($user['fullname'] ?? $user['username'] ?? '—')) ?></div>
        <div class="small"><b>Роль:</b> <?= htmlspecialchars((string)($user['role'] ?? '—')) ?></div>

        <div style="margin-top:14px;">
            <a class="btn" href="/logout.php">Выйти</a>
            <?php if (in_array(($user['role'] ?? ''), ['owner','admin'], true)): ?>
                <a class="btn" href="/admin/dashboard.php">Админка</a>
            <?php endif; ?>
        </div>

        <div class="small" style="margin-top:12px;">
            Этот кабинет сделан минимальным, чтобы не ломались редиректы.
            Дальше можем сюда добавить: “мои смены”, “профиль”, “telegram”, и т.д.
        </div>
    </div>
</div>

</body>
</html>