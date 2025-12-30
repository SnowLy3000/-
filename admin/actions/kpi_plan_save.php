<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на создание/изменение планов (slug: kpi_plan_save)
require_role('kpi_plan_save');

$user = current_user();

// 3. Сбор и очистка данных
$branchId = (int)($_POST['branch_id'] ?? 0);
$month    = (string)($_POST['month'] ?? date('Y-m'));
$plan     = (float)($_POST['plan_amount'] ?? 0);

// Если филиал не указан — возвращаемся назад
if ($branchId <= 0) {
    header('Location: /admin/index.php?page=kpi_plans&month='.$month);
    exit;
}

// Проверка формата месяца и корректности суммы
if (!preg_match('~^\d{4}-\d{2}$~', $month)) {
    $month = date('Y-m');
}
$monthDate = $month . '-01';

if ($plan < 0) $plan = 0;

try {
    // 4. Сохранение или обновление плана
    // ON DUPLICATE KEY UPDATE позволяет обновить существующий план на этот месяц
    $stmt = $pdo->prepare("
        INSERT INTO kpi_plans (branch_id, month_date, plan_amount, created_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            plan_amount = VALUES(plan_amount),
            updated_at = NOW()
    ");
    $stmt->execute([$branchId, $monthDate, $plan, (int)$user['id']]);

    // Возвращаемся на страницу планов с якорем на форму
    header('Location: /admin/index.php?page=kpi_plans&month='.$month.'&success=1#setPlan');
    exit;

} catch (PDOException $e) {
    die("Ошибка при сохранении плана продаж: " . $e->getMessage());
}