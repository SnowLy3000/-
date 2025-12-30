<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

// Устанавливаем заголовок, что мы возвращаем JSON
header('Content-Type: application/json');

require_auth();
require_role('Admin');

$shiftId = (int)($_POST['shift_id'] ?? 0);

if ($shiftId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM work_shifts WHERE id = ?");
        $success = $stmt->execute([$shiftId]);

        if ($success) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Не удалось удалить запись из базы']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['ok' => false, 'message' => 'ID смены не передан']);
}
exit;
