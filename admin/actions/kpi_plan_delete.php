<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на удаление планов (slug: kpi_plan_delete)
require_role('kpi_plan_delete');

// 3. Безопасность: работаем только через POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/index.php?page=kpi_plans');
    exit;
}

$branchId = (int)($_POST['branch_id'] ?? 0);
$month    = (string)($_POST['month'] ?? date('Y-m'));

// Если ID филиала некорректный — просто возвращаемся назад
if ($branchId <= 0) {
    header('Location: /admin/index.php?page=kpi_plans&month='.$month);
    exit;
}

// Проверка формата месяца (ГГГГ-ММ)
if (!preg_match('~^\d{4}-\d{2}$~', $month)) {
    $month = date('Y-m');
}
$monthDate = $month . '-01';

try {
    // Выполняем удаление
    $stmt = $pdo->prepare("DELETE FROM kpi_plans WHERE branch_id = ? AND month_date = ?");
    $stmt->execute([$branchId, $monthDate]);

    // Возвращаемся с флагом успеха
    header('Location: /admin/index.php?page=kpi_plans&month='.$month.'&deleted=1');
    exit;

} catch (PDOException $e) {
    die("Ошибка при удалении плана: " . $e->getMessage());
}