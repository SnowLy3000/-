<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();

// ТОЛЬКО OWNER может управлять администраторами
if (($_SESSION['user']['role'] ?? '') !== 'owner') {
    http_response_code(403);
    exit('Access denied. Owner only.');
}

$message = null;

/**
 * ОБРАБОТКА ФОРМ
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Создание администратора
    if ($action === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['permissions'] ?? [];

        if ($username === '' || $password === '') {
            $message = 'Заполните логин и пароль';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, status)
                    VALUES (?, ?, 'admin', 'active')
                ")->execute([$username, $hash]);

                $adminId = (int)$pdo->lastInsertId();

                foreach ($permissions as $code) {
                    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code=?");
                    $stmt->execute([$code]);
                    $permId = (int)$stmt->fetchColumn();

                    if ($permId) {
                        $pdo->prepare("
                            INSERT INTO user_permissions (user_id, permission_id)
                            VALUES (?, ?)
                        ")->execute([$adminId, $permId]);
                    }
                }

                $message = 'Администратор создан';
            } catch (Throwable $e) {
                $message = 'Ошибка: логин уже существует';
            }
        }
    }

    // Удаление администратора
    if ($action === 'delete_admin') {
        $adminId = (int)($_POST['admin_id'] ?? 0);

        $pdo->prepare("
            DELETE FROM users
            WHERE id = ? AND role = 'admin'
        ")->execute([$adminId]);

        $message = 'Администратор удалён';
    }
}

// Загружаем список администраторов
$admins = $pdo->query("
    SELECT id, username, role
    FROM users
    WHERE role IN ('owner','admin')
    ORDER BY role DESC, id ASC
")->fetchAll();

// Загружаем список прав
$permissions = $pdo->query("
    SELECT code, title
    FROM permissions
    ORDER BY id
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Администраторы</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/neon.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">
    <aside class="admin-menu neon">
        <a href="/admin/dashboard.php">← Dashboard</a>
        <a href="/admin/logout.php">Выйти</a>
    </aside>

    <main class="admin-main">
        <h1>Администраторы</h1>

        <?php if ($message): ?>
            <p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- СОЗДАНИЕ АДМИНИСТРАТОРА -->
        <div class="card neon">
            <h3>Создать администратора</h3>

            <form method="post">
                <input type="hidden" name="action" value="create_admin">

                <input name="username" placeholder="Логин администратора">
                <input type="password" name="password" placeholder="Пароль">

                <h4>Права:</h4>

                <?php foreach ($permissions as $p): ?>
                    <label style="display:block;margin:6px 0;">
                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($p['code']) ?>">
                        <?= htmlspecialchars($p['title']) ?>
                    </label>
                <?php endforeach; ?>

                <button class="btn" style="margin-top:10px;">Создать</button>
            </form>
        </div>

        <!-- СПИСОК АДМИНОВ -->
        <h3 style="margin-top:30px;">Существующие администраторы</h3>

        <?php foreach ($admins as $a): ?>
            <div class="card neon" style="margin-bottom:10px;">
                <b><?= htmlspecialchars($a['username'] ?? '—') ?></b>
                <div>Роль: <?= htmlspecialchars($a['role']) ?></div>

                <?php if ($a['role'] === 'admin'): ?>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="action" value="delete_admin">
                        <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
                        <button class="btn">🗑 Удалить</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    </main>
</div>

</body>
</html>
