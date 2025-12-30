<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/perms.php';

require_auth();

// Доступ: Администратор, Владелец или Бухгалтер
if (!has_role('Admin') && !has_role('Owner')) {
    http_response_code(403);
    exit('Access denied');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* 1. ПОЛУЧАЕМ СПИСОК ФИЛИАЛОВ */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

/* 2. ЗАГРУЖАЕМ НАСТРОЕКИ БОНУСНОЙ СЕТКИ */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_bonus_%'");
foreach ($stmt as $row) {
    $settings[$row['skey']] = (float)$row['svalue'];
}

/**
 * Логика определения % премии согласно твоим настройкам в kpi_settings
 */
function getBonusPercent(float $kpi, $settings): float {
    if ($kpi >= 130) return $settings['kpi_bonus_130'] ?? 30;
    if ($kpi >= 120) return $settings['kpi_bonus_120'] ?? 20;
    if ($kpi >= 110) return $settings['kpi_bonus_110'] ?? 10;
    if ($kpi >= 100) return $settings['kpi_bonus_100'] ?? 0;
    return 0;
}

$branchId = (int)($_GET['branch_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
$from = $month . '-01 00:00:00';
$to   = date('Y-m-t 23:59:59', strtotime($from));

/* 3. ПОЛУЧАЕМ ДАННЫЕ (ПРОДАЖИ + ЗАРПЛАТА С ТОВАРОВ) */
$rows = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS user_id,
            CONCAT(u.last_name,' ',u.first_name) AS name,
            COALESCE(SUM(s.total_amount),0) AS fact,
            (SELECT SUM(si2.salary_amount) 
             FROM sale_items si2 
             JOIN sales s2 ON s2.id = si2.sale_id 
             WHERE s2.user_id = u.id AND s2.branch_id = ? AND s2.created_at BETWEEN ? AND ?
            ) as clean_salary
        FROM users u
        LEFT JOIN sales s 
          ON s.user_id = u.id
         AND s.branch_id = ?
         AND s.created_at BETWEEN ? AND ?
        WHERE u.status='active'
        GROUP BY u.id
        HAVING fact > 0 OR clean_salary > 0
    ");
    $stmt->execute([$branchId, $from, $to, $branchId, $from, $to]);
    $rows = $stmt->fetchAll();
}

// Загружаем План филиала
$stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id=? AND DATE_FORMAT(month_date,'%Y-%m')=?");
$stmt->execute([$branchId, $month]);
$branchPlan = (float)$stmt->fetchColumn();

$countEmp = count($rows);
$planPerUser = $countEmp ? $branchPlan / $countEmp : 0;
?>

<style>
    .ledger-container { max-width: 1200px; margin: 0 auto; }
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); }
    .bonus-table { width: 100%; border-collapse: collapse; min-width: 950px; }
    .bonus-table th { padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); }
    .bonus-table td { padding: 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    
    .col-salary { color: #7CFF6B; font-weight: 800; }
    .col-bonus { color: #ffd166; font-weight: 800; }
    .col-total { background: rgba(120,90,255,0.08); font-weight: 900; color: #fff; font-size: 16px; }

    .badge-kpi { padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 900; display: inline-block; }
    .status-red { background: rgba(255,107,107,0.15); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.2); }
    .status-yellow { background: rgba(255,209,102,0.15); color: #ffd166; border: 1px solid rgba(255,209,102,0.2); }
    .status-green { background: rgba(124,255,107,0.15); color: #7CFF6B; border: 1px solid rgba(124,255,107,0.2); }
    
    .st-input { height: 46px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 15px; color: #fff; outline: none; transition: 0.3s; }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }

    .summary-card { background: rgba(120,90,255,0.05); border: 1px solid rgba(120,90,255,0.2); padding: 15px 25px; border-radius: 20px; display: flex; align-items: center; gap: 15px; }
</style>

<div class="ledger-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="margin:0; font-size: 28px;">💰 Текущая ведомость</h1>
            <p class="muted">Предварительный расчет выплат за выбранный период</p>
        </div>
        
        <?php if ($branchId): ?>
        <div class="summary-card">
            <div style="font-size: 24px;">🎯</div>
            <div>
                <span class="muted" style="font-size: 10px; display: block; text-transform: uppercase; font-weight: 800;">План на сотрудника:</span>
                <b style="color: #fff; font-size: 18px;"><?= number_format($planPerUser, 0, '.', ' ') ?> MDL</b>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom: 25px;">
        <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="kpi_bonus">
            <div style="flex: 1; min-width: 200px;">
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px; text-transform: uppercase;">Торговая точка</label>
                <select name="branch_id" class="st-input" required style="width: 100%;">
                    <option value="">— Выберите филиал —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id']==$branchId?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="muted" style="font-size: 10px; font-weight: 800; display:block; margin-bottom:8px; text-transform: uppercase;">Месяц</label>
                <input type="month" name="month" class="st-input" value="<?= h($month) ?>">
            </div>
            <button class="btn" style="height: 46px; padding: 0 30px; border-radius: 12px; font-weight: 800;">СФОРМИРОВАТЬ</button>
        </form>
    </div>

    <?php if (!$branchId): ?>
        <div class="card" style="text-align:center; padding: 80px 20px; border: 2px dashed rgba(255,255,255,0.05); background: none;">
            <div style="font-size: 40px; margin-bottom: 15px; opacity: 0.2;">📂</div>
            <h3 class="muted">Укажите филиал и период для расчета ведомости</h3>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="bonus-table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Личные продажи</th>
                        <th style="text-align: center;">% Плана</th>
                        <th>% Премии</th>
                        <th>ЗП с товаров</th>
                        <th>Сумма премии</th>
                        <th style="text-align: right; padding-right: 25px;">ИТОГО К ВЫДАЧЕ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($rows as $r):
                        $kpi = $planPerUser > 0 ? ($r['fact'] / $planPerUser) * 100 : 0;
                        $bp = getBonusPercent($kpi, $settings);
                        $bonusMoney = $r['fact'] * $bp / 100;
                        $cleanSalary = (float)$r['clean_salary'];
                        $totalToPay = $cleanSalary + $bonusMoney;
                        $grandTotal += $totalToPay;

                        // Цвет бейджа KPI
                        $cls = 'status-red';
                        if ($kpi >= 80) $cls = 'status-yellow';
                        if ($kpi >= 100) $cls = 'status-green';
                    ?>
                    <tr>
                        <td><b style="font-size: 15px;"><?= h($r['name']) ?></b></td>
                        <td style="font-weight: 600;"><?= number_format($r['fact'], 0, '.', ' ') ?> MDL</td>
                        <td style="text-align: center;">
                            <span class="badge-kpi <?= $cls ?>"><?= number_format($kpi, 1) ?>%</span>
                        </td>
                        <td style="font-weight: 700; color: #fff;"><?= $bp ?>%</td>
                        <td class="col-salary"><?= number_format($cleanSalary, 2, '.', ' ') ?> MDL</td>
                        <td class="col-bonus">+ <?= number_format($bonusMoney, 0, '.', ' ') ?> MDL</td>
                        <td class="col-total" style="text-align: right; padding-right: 25px;">
                            <?= number_format($totalToPay, 2, '.', ' ') ?> MDL
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(120, 90, 255, 0.1);">
                        <td colspan="6" style="text-align: right; font-weight: 800; padding: 25px; color: #b866ff; letter-spacing: 1px;">
                            СУММАРНЫЙ ФОНД К ВЫПЛАТЕ:
                        </td>
                        <td style="text-align: right; font-weight: 900; color: #7CFF6B; font-size: 22px; padding: 25px; padding-right: 25px;">
                            <?= number_format($grandTotal, 2, '.', ' ') ?> MDL
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <p class="muted" style="margin-top: 20px; font-size: 12px; text-align: right;">
            * Расчет является предварительным. Для фиксации выплат перейдите в раздел <b><a href="?page=kpi_fix" style="color:#785aff;">Фиксация месяца</a></b>.
        </p>
    <?php endif; ?>
</div>
