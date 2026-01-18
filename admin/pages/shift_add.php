<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ Admin на ключ доступа к сменам
require_role('manage_shifts');

$date     = $_POST['date'] ?? null;
$userId   = (int)($_POST['user_id'] ?? 0);
$branchId = (int)($_POST['branch_id'] ?? 0);

// Если данных нет, просто возвращаем назад
if (!$date || !$userId || !$branchId) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php?page=shifts'));
    exit;
}

try {
    // 1. Защита от дубля (чтобы не поставить одного человека дважды на один день в один филиал)
    $stmt = $pdo->prepare("
        SELECT id FROM work_shifts
        WHERE shift_date = ?
          AND user_id = ?
          AND branch_id = ?
        LIMIT 1
    ");
    $stmt->execute([$date, $userId, $branchId]);

    if (!$stmt->fetch()) {
        // 2. Добавление новой смены
        $stmt = $pdo->prepare("
            INSERT INTO work_shifts (shift_date, user_id, branch_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$date, $userId, $branchId]);
    }

    // Если запрос пришел через AJAX (как в основном интерфейсе графика)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['ok' => true]);
        exit;
    }

} catch (PDOException $e) {
    // Обработка ошибок для AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Обычный редирект для стандартных форм
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/index.php?page=shifts'));
exit;
