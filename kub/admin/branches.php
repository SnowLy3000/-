<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/delete_request.php';

require_admin();
require_permission('BRANCH_MANAGE');

$message = null;

/* =======================
   СОЗДАНИЕ / ОБНОВЛЕНИЕ
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {

    $id      = (int)($_POST['id'] ?? 0);
    $title   = trim($_POST['title'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $color   = $_POST['color'] ?? '#4CAF50';
    $active  = isset($_POST['active']) ? 1 : 0;

    if ($title === '') {
        $message = 'Название филиала обязательно';
    } else {
        if ($id > 0) {
            $pdo->prepare("
                UPDATE branches
                SET title=?, address=?, phone=?, color=?, active=?
                WHERE id=?
            ")->execute([$title, $address, $phone, $color, $active, $id]);

            $message = 'Филиал обновлён';
        } else {
            $pdo->prepare("
                INSERT INTO branches (title, address, phone, color, active)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$title, $address, $phone, $color, $active]);

            $message = 'Филиал добавлен';
        }
    }
}

/* =======================
   ЗАПРОС НА УДАЛЕНИЕ
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    require_permission('BRANCH_DELETE_REQUEST');

    $branchId = (int)$_POST['branch_id'];

    if ($branchId > 0) {
        $ok = createDeleteRequest(
            $pdo,
            'branch',
            $branchId,
            $_SESSION['user']['id']
        );

        $message = $ok
            ? 'Запрос на удаление отправлен'
            : 'Запрос уже существует';
    }
}

/* =======================
   СПИСОК ФИЛИАЛОВ (ТОЛЬКО АКТИВНЫЕ)
======================= */
$branches = $pdo->query("
    SELECT b.*,
           (
             SELECT COUNT(*)
             FROM delete_requests dr
             WHERE dr.entity_type = 'branch'
               AND dr.entity_id = b.id
               AND dr.status = 'pending'
           ) AS has_delete_request
    FROM branches b
    WHERE b.active = 1
    ORDER BY b.title
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Филиалы</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/branches.php"><b>🏬 Филиалы</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>🏬 Филиалы</h1>

<?php if ($message): ?>
<p style="color:#9ff"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<div class="card neon">
<h3>Добавить / редактировать</h3>

<form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id">

    <input name="title" placeholder="Название филиала" required>
    <input name="address" placeholder="Адрес">
    <input name="phone" placeholder="Телефон">

    <label>Цвет филиала:</label>
    <input type="color" name="color" value="#4CAF50">

    <label>
        <input type="checkbox" name="active" checked> Активен
    </label>

    <button class="btn">Сохранить</button>
</form>
</div>

<h3 style="margin-top:30px;">Список филиалов</h3>

<?php foreach ($branches as $b): ?>
<div class="card neon">
    <b><?= htmlspecialchars($b['title']) ?></b>

    <div><?= htmlspecialchars($b['address'] ?? '') ?></div>
    <div><?= htmlspecialchars($b['phone'] ?? '') ?></div>

    <div style="margin-top:6px">
        <span style="
            display:inline-block;
            width:14px;height:14px;
            background:<?= htmlspecialchars($b['color']) ?>;
            border-radius:50%;
            vertical-align:middle;
            margin-right:6px;
        "></span>
        <?= htmlspecialchars($b['color']) ?>
    </div>

    <?php if ($b['has_delete_request']): ?>
        <div style="color:#ff7777;margin-top:6px;">
            ⏳ Ожидает подтверждения владельца
        </div>
    <?php elseif (user_has('BRANCH_DELETE_REQUEST')): ?>
        <form method="post"
              onsubmit="return confirm('Отправить запрос на удаление филиала?');"
              style="margin-top:10px;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="branch_id" value="<?= (int)$b['id'] ?>">
            <button class="btn">🗑 Удалить</button>
        </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>