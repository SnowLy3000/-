<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

$token = $_POST['token'] ?? '';
if ($token === '') die('Нет токена');

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
$nowTime  = date('H:i:s');

/* ⏰ опоздание */
$lateMinutes = 0;
if ($nowTime > '09:00:00') {
    $lateMinutes = floor((strtotime($nowTime) - strtotime('09:00:00')) / 60);
}

/* запись отметки */
$pdo->prepare("
    INSERT INTO work_checkins (user_id, branch_id, work_date, checkin_time, late_minutes)
    VALUES (?, ?, ?, NOW(), ?)
")->execute([$userId, $branchId, $today, $lateMinutes]);

/* помечаем токен использованным */
$pdo->prepare("
    UPDATE branch_qr_tokens SET used=1 WHERE id=?
")->execute([$qr['id']]);

echo "✅ Смена отмечена. Хорошей работы!";