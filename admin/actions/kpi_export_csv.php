<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// 1. Проверка авторизации
require_auth();

// 2. Проверка права на экспорт данных
require_role('export_kpi');

$branchId = (int)($_GET['branch_id'] ?? 0);
$month    = $_GET['month'] ?? date('Y-m');

if (!$branchId) {
    die('Ошибка: Не выбран филиал для экспорта.');
}

// Подготовка даты для запроса
$monthQuery = $month . '-01';

$stmt = $pdo->prepare("
    SELECT 
        b.name AS branch,
        kb.month_date,
        CONCAT(u.last_name,' ',u.first_name) AS employee,
        kb.plan_amount,
        kb.sales_amount AS fact_amount, -- Исправил на sales_amount согласно твоей структуре kpi_bonuses
        kb.kpi_percent AS percent,
        kb.base_bonus,
        kb.final_bonus AS bonus_amount
    FROM kpi_bonuses kb
    JOIN users u ON u.id = kb.user_id
    JOIN branches b ON b.id = kb.branch_id
    WHERE kb.branch_id = ?
      AND DATE_FORMAT(kb.month_date,'%Y-%m') = ?
    ORDER BY employee
");
$stmt->execute([$branchId, $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die('Нет данных для экспорта за указанный период.');
}

// Заголовки для скачивания
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="kpi_report_'.$branchId.'_'.$month.'.csv"');

$out = fopen('php://output', 'w');

// Добавляем UTF-8 BOM для корректного отображения кириллицы в Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

/* Заголовки таблицы в CSV */
fputcsv($out, [
    'Филиал',
    'Месяц',
    'Сотрудник',
    'План',
    'Факт (Продажи)',
    'Выполнение %',
    'Базовый бонус',
    'Итого бонус'
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['branch'],
        date('M Y', strtotime($r['month_date'])),
        $r['employee'],
        number_format($r['plan_amount'], 2, '.', ''),
        number_format($r['fact_amount'], 2, '.', ''),
        number_format($r['percent'], 1, '.', '') . '%',
        number_format($r['base_bonus'], 2, '.', ''),
        number_format($r['bonus_amount'], 2, '.', ''),
    ]);
}

fclose($out);
exit;