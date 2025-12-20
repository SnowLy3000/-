<?php
/**
 * Страница: Отметиться (Check-in)
 */

require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/auth.php';

require_login();

$user = $_SESSION['user'];
$userId = (int)$user['id'];

$today = date('Y-m-d');
$now   = date('H:i:s');

$message = '';
$success = false;

/* =========================
   ФИЛИАЛЫ
========================= */
$branches = $pdo->query("
    SELECT id, title 
    FROM branches 
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ПРОВЕРКА: УЖЕ ОТМЕЧАЛСЯ?
========================= */
$st = $pdo->prepare("
    SELECT id, check_time 
    FROM work_checkins
    WHERE user_id = ? AND work_date = ?
    LIMIT 1
");
$st->execute([$userId, $today]);
$already = $st->fetch(PDO::FETCH_ASSOC);

/* =========================
   ОТПРАВКА
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already) {
    $branchId = (int)($_POST['branch_id'] ?? 0);

    if (!$branchId) {
        $message = 'Выберите филиал';
    } else {
        $st = $pdo->prepare("
            INSERT INTO work_checkins
            (user_id, branch_id, work_date, check_time)
            VALUES (?, ?, ?, ?)
        ");
        $st->execute([$userId, $branchId, $today, $now]);

        $success = true;
    }
}
?>

<h1>🟢 Отметиться</h1>

<p style="opacity:.7">
    Сегодня: <b><?= date('d.m.Y') ?></b>
</p>

<?php if ($already): ?>

    <div class="card">
        <h3>✅ Вы уже отметились</h3>
        <p>
            Время отметки:
            <b><?= substr($already['check_time'], 0, 5) ?></b>
        </p>

        <span class="badge green">
            Хорошей смены 👌
        </span>
    </div>

<?php elseif ($success): ?>

    <div class="card">
        <h3>🎉 Успешно!</h3>
        <p>
            Вы отметились в
            <b><?= substr($now, 0, 5) ?></b>
        </p>

        <span class="badge green">
            Смена началась
        </span>
    </div>

<?php else: ?>

    <div class="card" style="max-width:420px;">
        <h3>Отметка начала смены</h3>

        <?php if ($message): ?>
            <div class="badge red" style="margin-bottom:10px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="profile-field">
                <label>Филиал</label>
                <select name="branch_id" required>
                    <option value="">— выберите —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>">
                            <?= htmlspecialchars($b['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn-primary" type="submit">
                🕒 Отметиться
            </button>
        </form>
    </div>

<?php endif; ?>