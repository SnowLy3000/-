<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('view_reports');

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Загружаем данные филиала
$stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$stmt->execute([$branchId]);
$branchName = $stmt->fetchColumn() ?: 'Все филиалы';

// ОБНОВЛЕННЫЙ ЗАПРОС: Добавляем Cross-sales, Акции и Лояльность
$stmt = $pdo->prepare("
    SELECT
        CONCAT(u.last_name, ' ', u.first_name) as name,
        SUM(s.total_amount) as total,
        COUNT(DISTINCT s.id) as checks,
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
    WHERE s.branch_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total DESC
");
$stmt->execute([$branchId, $from, $to]);
$rows = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>KPI_Report_<?= h($branchName) ?>_<?= $from ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #000; background: #fff; padding: 10px; line-height: 1.4; font-size: 12px; }
        
        .no-print-zone { 
            background: #f8f9fa; padding: 15px; border-radius: 8px; 
            margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;
            border: 1px solid #dee2e6;
        }

        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .info-item b { display: block; font-size: 9px; color: #666; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f2f2f2; color: #000; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; border: 1px solid #ccc; }
        td { padding: 8px; border: 1px solid #ccc; }
        
        .total-row { background: #eee; font-weight: bold; }
        .badge { font-size: 10px; font-weight: bold; padding: 2px 5px; border: 1px solid #000; border-radius: 3px; }

        .signature-section { margin-top: 40px; display: flex; justify-content: space-between; gap: 50px; }
        .sig-box { flex: 1; border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 10px; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }

        .btn-print {
            background: #222; color: #fff; border: none; padding: 10px 20px; 
            border-radius: 6px; cursor: pointer; font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="no-print-zone no-print">
        <div><b>Печатная форма KPI.</b> Проверьте данные перед сохранением в PDF.</div>
        <button class="btn-print" onclick="window.print()">ПЕЧАТЬ / СОХРАНИТЬ PDF</button>
    </div>

    <div class="header">
        <h1>KPI СТАТИСТИКА ПРОДАЖ</h1>
        <div style="font-weight: bold;">KUB-SYSTEM / OFFICIAL</div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <b>Локация</b>
            <span style="font-size: 14px; font-weight: bold;"><?= htmlspecialchars($branchName) ?></span>
        </div>
        <div class="info-item" style="text-align: right;">
            <b>Период</b>
            <span><?= date('d.m.Y', strtotime($from)) ?> — <?= date('d.m.Y', strtotime($to)) ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Сотрудник</th>
                <th>Выручка (MDL)</th>
                <th>Чеки</th>
                <th>Ср. Чек</th>
                <th>Акция (%)</th>
                <th>Клиенты (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grandTotal = 0; $grandChecks = 0; $grandPromo = 0; $grandClients = 0;
            foreach ($rows as $r): 
                $avg = $r['checks'] ? $r['total'] / $r['checks'] : 0;
                $promoP = $r['checks'] ? ($r['promo_checks'] / $r['checks'] * 100) : 0;
                $clientP = $r['checks'] ? ($r['client_checks'] / $r['checks'] * 100) : 0;
                
                $grandTotal += $r['total'];
                $grandChecks += $r['checks'];
                $grandPromo += $r['promo_checks'];
                $grandClients += $r['client_checks'];
            ?>
            <tr>
                <td><b><?= htmlspecialchars($r['name']) ?></b></td>
                <td><?= number_format($r['total'], 0, '.', ' ') ?></td>
                <td><?= $r['checks'] ?></td>
                <td><?= number_format($avg, 0, '.', ' ') ?></td>
                <td><?= round($promoP, 1) ?>%</td>
                <td><?= round($clientP, 1) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>ИТОГО ПО ФИЛИАЛУ</td>
                <td><?= number_format($grandTotal, 0, '.', ' ') ?></td>
                <td><?= $grandChecks ?></td>
                <td><?= $grandChecks ? number_format($grandTotal / $grandChecks, 0, '.', ' ') : '0' ?></td>
                <td><?= $grandChecks ? round($grandPromo / $grandChecks * 100, 1) : 0 ?>%</td>
                <td><?= $grandChecks ? round($grandClients / $grandChecks * 100, 1) : 0 ?>%</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="sig-box">Ответственное лицо (подпись / ФИО)</div>
        <div class="sig-box">Проверено (Бухгалтерия)</div>
    </div>

    <div style="margin-top: 30px; font-size: 9px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px;">
        Дата генерации: <?= date('d.m.Y H:i') ?> | KUB CRM Analytics v2.0
    </div>

</body>
</html>