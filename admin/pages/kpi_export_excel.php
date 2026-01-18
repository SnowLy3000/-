<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

if (!$branchId) {
    exit('Ошибка: ID филиала не указан.');
}

// 1. Устанавливаем заголовки для выгрузки
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=KPI_Detailed_Export_' . $branchId . '_' . date('Y_m_d') . '.csv');

$out = fopen('php://output', 'w');
// BOM для корректного отображения кириллицы в Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// 2. Заголовок таблицы (теперь с Акциями и Клиентами)
fputcsv($out, [
    'Сотрудник', 
    'Выручка (L)', 
    'Кол-во чеков', 
    'Ср. чек (L)', 
    'Кросс-продажи (2+ тов.)',
    'Акционные чеки',
    'Чеки с клиентами (Лояльность)',
    'Период: ' . $from . ' - ' . $to
], ';'); // Используем точку с запятой для лучшей совместимости с Excel

// 3. Запрос данных с учетом новых модулей
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(u.last_name, ' ', u.first_name) as fio,
        SUM(s.total_amount) as total_sum,
        COUNT(DISTINCT s.id) as checks_count,
        -- Кросс-продажи
        COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM sale_items si2 WHERE si2.sale_id = s.id) >= 2 THEN s.id END) AS cross_sales,
        -- Акционные чеки
        COUNT(DISTINCT CASE WHEN (
            SELECT COUNT(*) FROM sale_items si3
            JOIN product_promotions pr ON pr.product_name = si3.product_name
            WHERE si3.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
        ) > 0 THEN s.id END) AS promo_checks,
        -- Чеки с клиентами
        COUNT(DISTINCT CASE WHEN s.client_id IS NOT NULL THEN s.id END) AS client_checks
    FROM users u
    JOIN sales s ON s.user_id = u.id
    WHERE s.branch_id = ?
      AND DATE(s.created_at) BETWEEN ? AND ?
      AND s.total_amount > 0
    GROUP BY u.id
    ORDER BY total_sum DESC
");

$stmt->execute([$branchId, $from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Запись данных
foreach ($rows as $row) {
    $avgCheck = $row['checks_count'] > 0 ? $row['total_sum'] / $row['checks_count'] : 0;
    
    fputcsv($out, [
        $row['fio'],
        round($row['total_sum'], 2),
        $row['checks_count'],
        round($avgCheck, 2),
        $row['cross_sales'],
        $row['promo_checks'],
        $row['client_checks']
    ], ';');
}

fclose($out);
exit;