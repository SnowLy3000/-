<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на управление сменами
require_role('manage_shifts');

$userId   = (int)($_POST['user_id'] ?? 0);
$branchId = (int)($_POST['branch_id'] ?? 0);
$date     = $_POST['shift_date'] ?? '';
$time     = $_POST['start_time'] ?? '';

// Базовая валидация данных
if (!$userId || !$branchId || !$date || !$time) {
    die('Ошибка: Все поля (сотрудник, филиал, дата, время) обязательны для заполнения.');
}

try {
    // 3. Проверка: не занят ли сотрудник в другом филиале в этот день?
    $check = $pdo->prepare("SELECT id FROM work_shifts WHERE user_id = ? AND shift_date = ? LIMIT 1");
    $check->execute([$userId, $date]);
    if ($check->fetch()) {
        die("Ошибка: У этого сотрудника уже запланирована смена на указанную дату ($date).");
    }

    // 4. Создание смены
    $stmt = $pdo->prepare("
        INSERT INTO work_shifts
        (user_id, branch_id, shift_date, start_time, status, created_by)
        VALUES (?, ?, ?, ?, 'scheduled', ?)
    ");

    $stmt->execute([
        $userId,
        $branchId,
        $date,
        $time,
        current_user()['id']
    ]);

    // Возвращаемся в календарь смен
    header('Location: /admin/index.php?page=shifts&success=1');
    exit;

} catch (PDOException $e) {
    die("Ошибка базы данных при создании смены: " . $e->getMessage());
}