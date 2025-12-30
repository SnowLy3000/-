<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ только для тех, кто может смотреть отчеты
require_role('view_reports');

$branchId = (int)($_GET['branch_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Загружаем данные филиала
$stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
$stmt->execute([$branchId]);
$branchName = $stmt->fetchColumn() ?: 'Все филиалы';

// Запрос данных (с использованием CEIL для соответствия твоей логике округления)
$stmt = $pdo->prepare("
    SELECT
        CONCAT(u.last_name, ' ', u.first_name) as name,
        SUM(CEIL(si.price - (si.price * si.discount / 100)) * si.quantity) as total,
        COUNT(DISTINCT s.id) as checks
    FROM users u
    JOIN sales s ON s.user_id = u.id
    JOIN sale_items si ON si.sale_id = s.id
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
    <title>KPI_Report_<?= $branchId ?>_<?= $from ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        
        body { font-family: 'Inter', Arial, sans-serif; color: #1a1a1a; background: #fff; padding: 20px; line-height: 1.6; }
        
        .no-print-zone { 
            background: #f4f4f9; padding: 15px; border-radius: 12px; 
            margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;
            border: 1px solid #ddd;
        }

        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 22px; text-transform: uppercase; letter-spacing: 1px; }
        
        .info-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; font-size: 14px; }
        .info-item b { display: block; text-transform: uppercase; font-size: 10px; color: #666; margin-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { background: #1a1a1a; color: #fff; text-align: left; padding: 12px; font-size: 11px; text-transform: uppercase; border: 1px solid #1a1a1a; }
        td { padding: 12px; border: 1px solid #eee; font-size: 13px; }
        
        .total-row { background: #f9f9f9; font-weight: bold; }
        .total-row td { border-top: 2px solid #1a1a1a; font-size: 15px; }

        .signature-zone { margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr; gap: 100px; }
        .sig-item { border-top: 1px solid #000; padding-top: 10px; font-size: 12px; text-align: center; }

        .footer { margin-top: 50px; font-size: 11px; color: #999; text-align: center; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .header { margin-top: 0; }
        }

        .btn-print {
            background: #000; color: #fff; border: none; padding: 12px 24px; 
            border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;
            display: flex; align-items: center; gap: 8px;
        }
    </style>
</head>
<body>

    <div class="no-print-zone no-print">
        <div style="font-size: 14px;">📄 <b>Предпросмотр отчёта.</b> Нажмите кнопку для печати или сохранения в PDF.</div>
        <button class="btn-print" onclick="window.print()">🖨️ ПЕЧАТЬ / PDF</button>
    </div>

    <div class="header">
        <h1>KPI REPORT / ОТЧЁТ ЭФФЕКТИВНОСТИ</h1>
        <div style="font-size: 12px; font-weight: bold;">ID: <?= $branchId ?>-<?= date('Ym') ?></div>
    </div>

    <div class="info-block">
        <div class="info-item">
            <b>Филиал / Подразделение</b>
            <span style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($branchName) ?></span>
        </div>
        <div class="info-item" style="text-align: right;">
            <b>Период отчета</b>
            <span style="font-size: 16px;"><?= date('d.m.Y', strtotime($from)) ?> — <?= date('d.m.Y', strtotime($to)) ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Сотрудник</th>
                <th>Объем продаж (MDL)</th>
                <th>Кол-во чеков</th>
                <th>Средний чек</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grandTotal = 0;
            $totalChecks = 0;
            foreach ($rows as $r): 
                $avg = $r['checks'] ? $r['total'] / $r['checks'] : 0;
                $grandTotal += $r['total'];
                $totalChecks += $r['checks'];
            ?>
            <tr>
                <td><b><?= htmlspecialchars($r['name']) ?></b></td>
                <td><?= number_format($r['total'], 2, '.', ' ') ?></td>
                <td><?= $r['checks'] ?></td>
                <td><?= number_format($avg, 2, '.', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>ИТОГО ПО ФИЛИАЛУ</td>
                <td><?= number_format($grandTotal, 2, '.', ' ') ?> MDL</td>
                <td><?= $totalChecks ?></td>
                <td><?= $totalChecks ? number_format($grandTotal / $totalChecks, 2, '.', ' ') : '0.00' ?></td>
            </tr>
        </tbody>
    </table>

    <div class="signature-zone">
        <div class="sig-item">Менеджер филиала (подпись / ФИО)</div>
        <div class="sig-item">Генеральный директор / Бухгалтерия</div>
    </div>

    <div class="footer">
        Документ сгенерирован в системе KUB CRM: <?= date('d.m.Y H:i:s') ?>. <br>
        Данные являются конфиденциальными и предназначены для внутреннего использования.
    </div>

</body>
</html>
