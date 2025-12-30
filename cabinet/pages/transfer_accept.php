<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

$transferId = (int)($_POST['transfer_id'] ?? 0);
if (!$transferId) exit('Ошибка');

// заявка
$stmt = $pdo->prepare("
    SELECT *
    FROM shift_transfers
    WHERE id = ?
      AND to_user_id = ?
      AND status = 'pending'
");
$stmt->execute([$transferId, $user['id']]);
$tr = $stmt->fetch();

if (!$tr) exit('Недоступно');

// передаём смену
$pdo->prepare("
    UPDATE shift_sessions
    SET user_id = ?
    WHERE id = ?
")->execute([$user['id'], $tr['session_id']]);

// закрываем заявку
$pdo->prepare("
    UPDATE shift_transfers
    SET status = 'accepted', confirmed_at = NOW()
    WHERE id = ?
")->execute([$transferId]);

header('Location: /cabinet/index.php?page=transfers');
exit;