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

/* --- Загрузка филиалов --- */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* --- Запрос данных --- */
$sql = "SELECT kb.*, u.first_name, u.last_name, b.name AS branch_name
        FROM kpi_bonuses kb
        JOIN users u    ON u.id = kb.user_id
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

function getKpiClass(float $kpi): string {
    if ($kpi >= 100) return 'kpi-green'; 
    if ($kpi >= 80)  return 'kpi-yellow'; 
    return 'kpi-red';
}
?>

<style>
    .archive-container { font-family: 'Inter', sans-serif; max-width: 1200px; margin: 0 auto; color: #fff; }
    
    /* КОМПАКТНЫЙ ФИЛЬТР */
    .filter-card-mini { 
        background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 20px; padding: 15px 25px; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center; gap: 15px;
    }
    .st-select, .st-input { 
        height: 36px; background: #0b0b12; border: 1px solid #333; 
        border-radius: 10px; padding: 0 12px; color: #fff; outline: none; font-size: 13px;
    }
    .btn-load { 
        height: 36px; background: #785aff; color: #fff; border: none; 
        border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; font-size: 13px;
    }

    /* ТАБЛИЦА */
    .ledger-box { background: rgba(255, 255, 255, 0.01); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
    .ledger-table { width: 100%; border-collapse: collapse; }
    .ledger-table th { 
        padding: 12px 15px; text-align: left; font-size: 9px; text-transform: uppercase; 
        color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.08); letter-spacing: 0.5px;
    }
    .ledger-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    /* KPI ЦВЕТА */
    .kpi-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .kpi-green { color: #7CFF6B; } .kpi-green .kpi-dot { background: #7CFF6B; box-shadow: 0 0 8px #7CFF6B; }
    .kpi-yellow { color: #ffd166; } .kpi-yellow .kpi-dot { background: #ffd166; }
    .kpi-red { color: #ff6b6b; } .kpi-red .kpi-dot { background: #ff6b6b; }

    .money-bonus { color: #ffd166; font-weight: 700; }
    .money-salary { color: #7CFF6B; font-weight: 700; }
    .total-cell { background: rgba(120,90,255,0.04); font-weight: 800; text-align: right !important; }

    .status-fixed { background: rgba(124, 255, 107, 0.05); color: #7CFF6B; padding: 6px 15px; border-radius: 12px; font-size: 11px; font-weight: 800; border: 1px solid rgba(124, 255, 107, 0.1); }
</style>

<div class="archive-container">
    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; background: rgba(120,90,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">📒</div>
        <div>
            <h1 style="margin:0; font-size: 20px; font-weight: 800;">Архив начислений</h1>
            <p style="margin:0; font-size: 12px; opacity: 0.4;">История выплат за закрытые периоды</p>
        </div>
    </div>

    <div class="filter-card-mini">
        <form method="get" style="display: flex; gap: 10px; width: 100%;">
            <input type="hidden" name="page" value="kpi_bonuses">
            <select name="branch_id" class="st-select" style="flex: 1;">
                <option value="0">Все филиалы</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="month" name="month" class="st-input" value="<?= h($monthInput) ?>">
            <button class="btn-load">Загрузить</button>
        </form>
    </div>

    <div class="ledger-box">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>Сотрудник / Филиал</th>
                    <th>Продажи (Факт)</th>
                    <th>Выполнение %</th>
                    <th>ЗП (Товары)</th>
                    <th>Бонус (План)</th>
                    <th style="text-align: right;">Итого к выдаче</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 60px; opacity: 0.3;">Архивных данных за этот период нет</td></tr>
                <?php else: ?>
                    <?php 
                    $totalMonthFund = 0;
                    foreach ($rows as $r): 
                        $salaryPart = (float)($r['salary_amount'] ?? 0);
                        $bonusPart = (float)$r['final_bonus'];
                        $rowTotal = $salaryPart + $bonusPart;
                        $totalMonthFund += $rowTotal;
                        $kpiClass = getKpiClass($r['kpi_percent']);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?= h($r['last_name'].' '.$r['first_name']) ?></div>
                            <div style="font-size: 10px; opacity: 0.4;"><?= h($r['branch_name']) ?> (ID #<?= $r['id'] ?>)</div>
                        </td>
                        <td style="font-weight: 600;"><?= number_format($r['sales_amount'], 0, '.', ' ') ?> L</td>
                        <td class="<?= $kpiClass ?>" style="font-weight: 800;">
                            <span class="kpi-dot"></span><?= number_format($r['kpi_percent'], 1) ?>%
                        </td>
                        <td class="money-salary"><?= number_format($salaryPart, 0, '.', ' ') ?> L</td>
                        <td class="money-bonus">+ <?= number_format($bonusPart, 0, '.', ' ') ?> L</td>
                        <td class="total-cell">
                            <?= number_format($rowTotal, 0, '.', ' ') ?> L
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot>
                <tr style="background: rgba(120, 90, 255, 0.05);">
                    <td colspan="5" style="text-align: right; padding: 15px; font-weight: 700; font-size: 10px; opacity: 0.5; text-transform: uppercase;">Общий фонд выплат за месяц:</td>
                    <td style="text-align: right; padding: 15px; font-weight: 900; color: #7CFF6B; font-size: 18px;">
                        <?= number_format($totalMonthFund, 0, '.', ' ') ?> L
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($rows): ?>
    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
        <div class="status-fixed">✔️ ВЕДОМОСТЬ ПОДТВЕРЖДЕНА И ЗАКРЫТА</div>
    </div>
    <?php endif; ?>
</div>