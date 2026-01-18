<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ Admin на ключ доступа к сменам
require_role('manage_shifts');

$shiftId = (int)($_POST['shift_id'] ?? 0);

if ($shiftId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM work_shifts WHERE id = ?");
        $stmt->execute([$shiftId]);
        
        // Если запрос пришел через AJAX (как в твоем shifts.php), возвращаем JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['ok' => true]);
            exit;
        }
    } catch (PDOException $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Если это обычный переход по ссылке
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php?page=shifts'));
exit;