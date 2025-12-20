<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('SCHEDULE_MANAGE');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$do = $_POST['do'] ?? '';
$date = trim($_POST['date'] ?? '');
$userId = (int)($_POST['user_id'] ?? 0);
$branchId = (int)($_POST['branch_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Некорректная дата');
}
if ($branchId <= 0) {
    http_response_code(400);
    exit('Некорректный филиал');
}
if ($do === 'add' && $userId <= 0) {
    http_response_code(400);
    exit('Некорректный сотрудник');
}

if ($do === 'add') {
    $max = (int)($_POST['max'] ?? 1);
    if ($max < 1) $max = 1;
    if ($max > 10) $max = 10;

    // 1) конфликт: сотрудник уже где-то работает в этот день (в другом филиале)
    $stmt = $pdo->prepare("
        SELECT ws.branch_id, COALESCE(b.title,'—') AS bname
        FROM work_schedule ws
        JOIN branches b ON b.id = ws.branch_id
        WHERE ws.user_id = ?
          AND ws.work_date = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $date]);
    $conf = $stmt->fetch();

    if ($conf && (int)$conf['branch_id'] !== $branchId) {
        http_response_code(409);
        exit('Конфликт: сотрудник уже назначен в этот день в филиал: ' . $conf['bname']);
    }

    // 2) лимит на день (в выбранном филиале)
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM work_schedule
        WHERE branch_id = ?
          AND work_date = ?
    ");
    $stmt->execute([$branchId, $date]);
    $cnt = (int)$stmt->fetchColumn();

    if ($cnt >= $max) {
        http_response_code(409);
        exit('Этот день уже полный (лимит: '.$max.')');
    }

    // 3) вставка (у тебя уже есть uniq_user_date(user_id, work_date), поэтому дубль “сам себя” не добавит
    try {
        $stmt = $pdo->prepare("
            INSERT INTO work_schedule (branch_id, user_id, work_date)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$branchId, $userId, $date]);
    } catch (Throwable $e) {
        http_response_code(409);
        exit('Не удалось добавить (возможно, уже есть такая смена)');
    }

    exit('OK');
}

if ($do === 'delete') {
    if ($userId <= 0) {
        http_response_code(400);
        exit('Некорректный сотрудник');
    }

    $stmt = $pdo->prepare("
        DELETE FROM work_schedule
        WHERE branch_id = ?
          AND user_id = ?
          AND work_date = ?
        LIMIT 1
    ");
    $stmt->execute([$branchId, $userId, $date]);

    exit('OK');
}

http_response_code(400);
exit('Некорректное действие');