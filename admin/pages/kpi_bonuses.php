<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ только для Администратора и Владельца
if (!has_role('Admin') && !has_role('Owner')) {
    http_response_code(403);
    exit('Access denied');
}

$monthInput = $_GET['month'] ?? date('Y-m');
$monthDate  = $monthInput . '-01';
$branchId   = (int)($_GET['branch_id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --- Загрузка филиалов для фильтра --- */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* --- Запрос данных из архива --- */
$sql = "SELECT kb.*, u.first_name, u.last_name, b.name AS branch_name
        FROM kpi_bonuses kb
        JOIN users u   ON u.id = kb.user_id
        JOIN branches b ON b.id = kb.branch_id
        WHERE kb.month_date = ?";
$params = [$monthDate];

if ($branchId > 0) {
    $sql .= " AND kb.branch_id = ?";
    $params[] = $branchId;
}

$sql .= " ORDER BY b.name, kb.kpi_percent DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function getKpiColor(float $kpi): string {
    if ($kpi >= 100) return '#7CFF6B'; // Зеленый
    if ($kpi >= 80)  return '#ffd166'; // Желтый
    return '#ff6b6b';                  // Красный
}
?>

<style>
    .archive-container { max-width: 1200px; margin: 0 auto; }
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 0 0 24px 24px; }
    .ledger-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
    
    .ledger-table th { 
        padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; 
        color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); 
        background: rgba(255,255,255,0.02); letter-spacing: 1px;
    }
    .ledger-table td { padding: 16px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; white-space: nowrap; }
    
    .st-input, .st-select { 
        height: 46px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 12px; padding: 0 15px; color: #fff; outline: none; font-size: 14px;
    }
    .st-input:focus, .st-select:focus { border-color: #785aff; }

    .salary-box { color: #7CFF6B; font-weight: 800; }
    .bonus-box { color: #ffd166; font-weight: 800; }
    .total-sum-cell { background: rgba(120,90,255,0.05); font-weight: 900; font-size: 15px; text-align: right !important; color: #fff; }
    
    .status-fixed { background: rgba(0, 200, 81, 0.1); color: #00c851; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; }
</style>

<div class="archive-container">
    <div class="card" style="margin-bottom: 25px; border-radius: 24px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin:0; font-size: 26px;">📒 Архив начислений</h1>
                <p class="muted" style="margin-top: 5px;">История выплат и итоговые ведомости за закрытые периоды</p>
            </div>

            <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <input type="hidden" name="page" value="kpi_bonuses">
                <select name="branch_id" class="st-select">
                    <option value="0">Все филиалы</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="month" name="month" class="st-input" value="<?= h($monthInput) ?>">
                <button class="btn" style="height: 46px; padding: 0 25px;">Загрузить</button>
            </form>
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; border-radius: 24px; border: 1px solid rgba(255,255,255,0.05);">
        <div class="table-responsive">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th style="width: 250px;">Сотрудник</th>
                        <th>Филиал</th>
                        <th>Выручка (Факт)</th>
                        <th>Выполнение %</th>
                        <th>Чистая ЗП</th>
                        <th>Бонус</th>
                        <th style="text-align: right; padding-right: 25px;">Итого к выплате</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 100px 20px; opacity: 0.3; font-size: 16px;">За выбранный период данных не найдено</td></tr>
                    <?php else: ?>
                        <?php 
                        $totalMonthFund = 0;
                        foreach ($rows as $r): 
                            $salaryPart = (float)($r['salary_amount'] ?? 0);
                            $bonusPart = (float)$r['final_bonus'];
                            $rowTotal = $salaryPart + $bonusPart;
                            $totalMonthFund += $rowTotal;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 800; font-size: 15px; color: #fff;"><?= h($r['last_name'].' '.$r['first_name']) ?></div>
                                <div class="muted" style="font-size: 11px; margin-top: 3px;">ID выписки: #<?= $r['id'] ?></div>
                            </td>
                            <td><span style="opacity: 0.7;"><?= h($r['branch_name']) ?></span></td>
                            <td style="font-weight: 600;"><?= number_format($r['sales_amount'], 0, '.', ' ') ?> MDL</td>
                            <td style="font-weight: 900; color: <?= getKpiColor($r['kpi_percent']) ?>;">
                                <?= number_format($r['kpi_percent'], 1) ?>%
                            </td>
                            <td class="salary-box"><?= number_format($salaryPart, 2, '.', ' ') ?> MDL</td>
                            <td class="bonus-box">+ <?= number_format($bonusPart, 0, '.', ' ') ?> MDL</td>
                            <td class="total-sum-cell" style="padding-right: 25px;">
                                <?= number_format($rowTotal, 2, '.', ' ') ?> MDL
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr style="background: rgba(120, 90, 255, 0.08);">
                        <td colspan="6" style="text-align: right; padding: 25px; font-weight: 800; color: #b866ff; font-size: 14px; letter-spacing: 1px;">
                            СУММАРНЫЙ ФОНД ВЫПЛАТ (ФОТ):
                        </td>
                        <td style="text-align: right; padding: 25px; padding-right: 25px; font-weight: 900; color: #7CFF6B; font-size: 20px; text-shadow: 0 0 15px rgba(124, 255, 107, 0.3);">
                            <?= number_format($totalMonthFund, 2, '.', ' ') ?> MDL
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php if ($rows): ?>
    <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
        <div class="status-fixed">✔️ ВСЕ ДАННЫЕ ЗАФИКСИРОВАНЫ И ПРОВЕРЕНЫ</div>
    </div>
    <?php endif; ?>
</div>
