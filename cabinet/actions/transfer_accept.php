<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

$transferId = (int)($_POST['transfer_id'] ?? 0);
if (!$transferId) {
    $_SESSION['error'] = 'Некорректный запрос';
    header('Location: /cabinet/index.php?page=transfers');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) берём заявку
    $stmt = $pdo->prepare("
        SELECT *
        FROM shift_transfers
        WHERE id = ?
          AND to_user_id = ?
          AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$transferId, $user['id']]);
    $tr = $stmt->fetch();

    if (!$tr) {
        throw new Exception('Заявка недоступна');
    }

    $branchId  = (int)$tr['branch_id'];
    $shiftDate = $tr['shift_date'];
    $fromUser  = (int)$tr['from_user_id'];
    $fromSess  = (int)($tr['from_session_id'] ?? 0);

    // 2) закрываем активную смену у отправителя
    if ($fromSess > 0) {
        $stmt = $pdo->prepare("
            UPDATE shift_sessions
            SET checkout_at = NOW()
            WHERE id = ?
              AND user_id = ?
              AND checkout_at IS NULL
        ");
        $stmt->execute([$fromSess, $fromUser]);
    } else {
        // fallback если вдруг from_session_id пустой
        $stmt = $pdo->prepare("
            UPDATE shift_sessions
            SET checkout_at = NOW()
            WHERE user_id = ?
              AND branch_id = ?
              AND shift_date = ?
              AND checkout_at IS NULL
        ");
        $stmt->execute([$fromUser, $branchId, $shiftDate]);
    }

    // 3) создаём/обновляем сессию у получателя (без дублей по uniq_user_date)
    $stmt = $pdo->prepare("
        SELECT id
        FROM shift_sessions
        WHERE user_id = ?
          AND shift_date = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $shiftDate]);
    $existing = $stmt->fetch();

    if ($existing) {
        // если запись уже есть — обновляем, а не вставляем (чтобы не было Duplicate)
        $stmt = $pdo->prepare("
            UPDATE shift_sessions
            SET branch_id = ?,
                checkin_at = NOW(),
                checkout_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$branchId, (int)$existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO shift_sessions (user_id, branch_id, shift_date, checkin_at, created_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user['id'], $branchId, $shiftDate]);
    }

    // 4) переносим смену в графике (чтобы dashboard тоже видел)
    $stmt = $pdo->prepare("
        UPDATE work_shifts
        SET user_id = ?
        WHERE user_id = ?
          AND branch_id = ?
          AND shift_date = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $fromUser, $branchId, $shiftDate]);

    // 5) закрываем заявку
    $stmt = $pdo->prepare("
        UPDATE shift_transfers
        SET status = 'accepted',
            confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$transferId]);

    // 6) уведомление отправителю
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, created_at)
        VALUES (?, 'transfer', 'Вашу смену приняли', NOW())
    ");
    $stmt->execute([$fromUser]);

    $pdo->commit();

    $_SESSION['success'] = 'Смена успешно принята';
    header('Location: /cabinet/index.php?page=checkin');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header('Location: /cabinet/index.php?page=transfers');
    exit;
}