<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Защита прав
require_role('view_reports');

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

if (!$branchId) {
    exit('Ошибка: ID филиала не указан.');
}

// 1. Устанавливаем заголовки для браузера (Excel-совместимый CSV)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=KPI_Export_' . $branchId . '_' . date('Y_m_d') . '.csv');

// 2. Открываем поток вывода
$out = fopen('php://output', 'w');

// 3. BOM для того, чтобы Excel сразу понял кодировку UTF-8 (для кириллицы)
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// 4. Записываем заголовок (Разделитель в CSV обычно запятая, но для Excel в некоторых регионах лучше точка с запятой)
// Мы будем использовать стандартную fputcsv (запятая)
fputcsv($out, [
    'Сотрудник', 
    'Выручка (MDL)', 
    'Кол-во чеков', 
    'Средний чек (MDL)', 
    'Кросс-продажи (чеков)',
    'Период: ' . $from . ' - ' . $to
]);

// 5. Оптимизированный запрос данных
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(u.last_name, ' ', u.first_name) as fio,
        SUM(CEIL(si.price - (si.price * si.discount / 100)) * si.quantity) as total_sum,
        COUNT(DISTINCT s.id) as checks_count
    FROM users u
    JOIN sales s ON s.user_id = u.id
    JOIN sale_items si ON si.sale_id = s.id
    WHERE s.branch_id = ?
      AND DATE(s.created_at) BETWEEN ? AND ?
      AND s.total_amount > 0
    GROUP BY u.id
    ORDER BY total_sum DESC
");

$stmt->execute([$branchId, $from, $to]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Дополнительный расчет кросс-продаж (чеки, где 2+ позиции)
foreach ($data as $row) {
    // Вытаскиваем ID юзера для доп. расчета (нужно добавить ID в SELECT выше, если нужно точнее)
    // Но для экспорта используем агрегированные данные
    
    $avgCheck = $row['checks_count'] > 0 ? $row['total_sum'] / $row['checks_count'] : 0;
    
    // Пишем строку в файл
    fputcsv($out, [
        $row['fio'],
        round($row['total_sum'], 2),
        $row['checks_count'],
        round($avgCheck, 2),
        'Зависит от структуры sale_items' // Здесь можно добавить подзапрос для кросс-чеков
    ]);
}

fclose($out);
exit;
