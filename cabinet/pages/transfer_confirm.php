<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

$user = current_user();

$shiftId  = (int)($_GET['shift_id'] ?? 0);
$toUserId = (int)($_GET['to_user_id'] ?? 0);

if (!$shiftId || !$toUserId) {
    exit('Некорректные данные');
}

// получаем информацию
$stmt = $pdo->prepare("
    SELECT ws.id, b.name AS branch_name,
           u.first_name, u.last_name
    FROM work_shifts ws
    JOIN branches b ON b.id = ws.branch_id
    JOIN users u ON u.id = ?
    WHERE ws.id = ? AND ws.user_id = ?
");
$stmt->execute([$toUserId, $shiftId, $user['id']]);
$info = $stmt->fetch();

if (!$info) {
    exit('Смена недоступна для передачи');
}
?>

<div class="card">
    <h2>Подтверждение передачи смены</h2>
</div>

<div class="card">
    <p>
        Вы собираетесь передать смену сотруднику:<br>
        <b><?= htmlspecialchars($info['last_name'].' '.$info['first_name']) ?></b>
    </p>

    <p>
        <b>Филиал:</b> <?= htmlspecialchars($info['branch_name']) ?>
    </p>

    <div class="muted">
        После подтверждения вы больше не будете ответственны
        за эту смену. Действие нельзя отменить.
    </div>
</div>

<form method="post" action="/cabinet/pages/transfer_request.php" class="card">

    <input type="hidden" name="shift_id" value="<?= $shiftId ?>">
    <input type="hidden" name="to_user_id" value="<?= $toUserId ?>">

    <label style="display:block;margin-bottom:10px;">
        <input type="checkbox" name="confirm" value="1" required>
        Я подтверждаю передачу смены и понимаю последствия
    </label>

    <button class="btn">Подтвердить передачу</button>
</form>