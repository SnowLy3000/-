<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на фиксацию (архивацию) итогов месяца
require_role('kpi_fix_save');

// Проверяем, что данные пришли методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Ошибка: Недопустимый метод запроса.");
}

// Собираем и валидируем данные
$branchId     = (int)($_POST['branch_id'] ?? 0);
$monthRaw     = $_POST['month'] ?? ''; // Ожидаем Y-m
$planAmount   = (float)($_POST['plan'] ?? 0);
$factAmount   = (float)($_POST['fact'] ?? 0);
$kpiPercent   = (float)($_POST['kpi'] ?? 0);
$bonusPercent = (float)($_POST['bonus_percent'] ?? 0);
$bonusAmount  = (float)($_POST['bonus_amount'] ?? 0);
$fixedBy      = $_SESSION['user']['id'];

if (!$branchId || empty($monthRaw)) {
    die("Ошибка: Недостаточно данных для фиксации периода.");
}

$monthDate = $monthRaw . '-01';

try {
    // Используем INSERT INTO ... ON DUPLICATE KEY UPDATE, 
    // чтобы не плодить дубликаты, если админ нажмет "Зафиксировать" повторно
    $stmt = $pdo->prepare("
        INSERT INTO kpi_facts
        (branch_id, month_date, plan_amount, fact_amount, kpi_percent,
         bonus_percent, bonus_amount, fixed_by, fixed_at)
        VALUES (?,?,?,?,?,?,?,?, NOW())
        ON DUPLICATE KEY UPDATE
            plan_amount = VALUES(plan_amount),
            fact_amount = VALUES(fact_amount),
            kpi_percent = VALUES(kpi_percent),
            bonus_percent = VALUES(bonus_percent),
            bonus_amount = VALUES(bonus_amount),
            fixed_by = VALUES(fixed_by),
            fixed_at = NOW()
    ");

    $stmt->execute([
        $branchId,
        $monthDate,
        $planAmount,
        $factAmount,
        $kpiPercent,
        $bonusPercent,
        $bonusAmount,
        $fixedBy
    ]);

    // Возвращаемся назад с флагом успеха
    header('Location: /admin/index.php?page=kpi_fix&branch_id='.$branchId.'&month='.$monthRaw.'&success=1');
    exit;

} catch (PDOException $e) {
    die("Ошибка при сохранении фиксации: " . $e->getMessage());
}