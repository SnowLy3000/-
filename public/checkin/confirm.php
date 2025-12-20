<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$token = $_GET['token'] ?? '';
if ($token === '') die('Нет токена');

// ищем токен
$stmt = $pdo->prepare("
    SELECT * FROM branch_qr_tokens
    WHERE token=? AND used=0 AND expires_at >= NOW()
");
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) die('Токен недействителен');

$userId   = $_SESSION['user']['id'];
$branchId = $qr['branch_id'];
$today    = date('Y-m-d');

/* 🔒 ПРОВЕРКА: есть ли смена сегодня */
$stmt = $pdo->prepare("
    SELECT 1 FROM work_schedule
    WHERE user_id=? AND branch_id=? AND work_date=?
");
$stmt->execute([$userId, $branchId, $today]);
if (!$stmt->fetch()) {
    die('У вас нет смены в этом филиале сегодня');
}

/* ⏱ ПРОВЕРКА ВРЕМЕНИ */
$now = date('H:i');
if ($now < '08:30' || $now > '09:15') {
    die('Отметка доступна с 08:30 до 09:15');
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Отметка смены</title>
</head>
<body>

<h2>Вы на работе?</h2>
<form method="post" action="/checkin/do_checkin.php">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <button style="font-size:20px;padding:15px">
        ✅ Отметиться
    </button>
</form>

</body>
</html>