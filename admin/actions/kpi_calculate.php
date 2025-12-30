<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// 1. Проверка авторизации
require_auth();

// 2. Гибкая проверка права вместо жестких ролей
require_role('kpi_calculate');

// 3. Получаем месяц. Если не передан — берем текущий
$month = $_POST['month'] ?? date('Y-m-01'); // формат 2025-01-01

// 1️⃣ Берём планы филиалов
$plansStmt = $pdo->prepare("
    SELECT kp.branch_id, kp.plan_amount, b.name AS branch_name
    FROM kpi_plans kp
    JOIN branches b ON b.id = kp.branch_id
    WHERE kp.month_date = ?
");
$plansStmt->execute([$month]);
$plans = $plansStmt->fetchAll();

if (!$plans) {
    die("Ошибка: Планы на выбранный месяц ($month) не установлены. Сначала заполните планы продаж.");
}

foreach ($plans as $plan) {
    $branchId   = $plan['branch_id'];
    $planAmount = (float)$plan['plan_amount'];

    // 2️⃣ Все сотрудники филиала (только активные)
    $usersStmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        WHERE u.branch_id = ? AND u.status = 'active'
    ");
    $usersStmt->execute([$branchId]);
    $users = $usersStmt->fetchAll();

    foreach ($users as $u) {
        $userId = $u['id'];

        // 3️⃣ Продажи сотрудника за месяц (сумма всех завершенных продаж)
        $salesStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM sales
            WHERE user_id = ?
              AND DATE_FORMAT(created_at, '%Y-%m-01') = ?
        ");
        $salesStmt->execute([$userId, $month]);
        $salesAmount = (float)$salesStmt->fetchColumn();

        // 4️⃣ KPI % (Выполнение плана)
        $kpiPercent = $planAmount > 0
            ? round(($salesAmount / $planAmount) * 100, 2)
            : 0;

        // 5️⃣ Расчет бонуса
        // Здесь можно добавить логику из kpi_settings (например, разные проценты для разных категорий)
        $baseBonus  = 1000; 
        $finalBonus = round($baseBonus * ($kpiPercent / 100), 2);

        // 6️⃣ Сохраняем или обновляем результат
        $insert = $pdo->prepare("
            INSERT INTO kpi_bonuses
                (user_id, branch_id, month_date, sales_amount, plan_amount,
                 kpi_percent, base_bonus, final_bonus)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                sales_amount = VALUES(sales_amount),
                plan_amount  = VALUES(plan_amount),
                kpi_percent  = VALUES(kpi_percent),
                final_bonus  = VALUES(final_bonus)
        ");

        $insert->execute([
            $userId,
            $branchId,
            $month,
            $salesAmount,
            $planAmount,
            $kpiPercent,
            $baseBonus,
            $finalBonus
        ]);
    }
}

// Если это AJAX запрос
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode(['ok' => true, 'message' => 'KPI успешно пересчитан']);
} else {
    // Если обычный переход — редирект назад на страницу KPI
    header("Location: /admin/index.php?page=kpi&month=" . urlencode($month) . "&success=1");
}
exit;