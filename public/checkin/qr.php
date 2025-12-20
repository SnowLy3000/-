<?php
require __DIR__ . '/../includes/db.php';

$branchId = (int)($_GET['branch'] ?? 0);
if (!$branchId) die('Неверный филиал');

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 60);

$pdo->prepare("
    INSERT INTO branch_qr_tokens (branch_id, token, expires_at)
    VALUES (?, ?, ?)
")->execute([$branchId, $token, $expires]);

header("Location: /checkin/confirm.php?token=$token");
exit;