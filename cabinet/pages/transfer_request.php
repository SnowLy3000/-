<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

// активная смена
$stmt = $pdo->prepare("
    SELECT id
    FROM shift_sessions
    WHERE user_id = ?
      AND checkout_at IS NULL
    LIMIT 1
");
$stmt->execute([$user['id']]);
$session = $stmt->fetch();

if (!$session) exit('Нет активной смены');

// сотрудники
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM users
    WHERE status = 'active'
      AND id != ?
");
$stmt->execute([$user['id']]);
$users = $stmt->fetchAll();
?>

<div class="card">
<h2>Передача смены</h2>

<form method="post" action="/cabinet/actions/transfer_request.php">
    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">

    <select name="to_user_id" required>
        <option value="">— выбрать сотрудника —</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['last_name'].' '.$u['first_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-warning" style="margin-top:10px;">
        Передать смену
    </button>
</form>
</div>