<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
require_role('manage_kpi_plans');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Настройка месяца */
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('~^\d{4}-\d{2}$~', $month)) $month = date('Y-m');
$monthDate = $month . '-01';

/* Загрузка планов и факта продаж за прошлый месяц */
$prevMonth = date('Y-m', strtotime($monthDate . " -1 month"));

$stmt = $pdo->prepare("
    SELECT b.id, b.name,
           (SELECT plan_amount FROM kpi_plans WHERE branch_id = b.id AND month_date = ?) as current_plan,
           (SELECT SUM(total_amount) FROM sales WHERE branch_id = b.id AND DATE_FORMAT(created_at, '%Y-%m') = ?) as prev_fact
    FROM branches b
    ORDER BY b.name
");
$stmt->execute([$monthDate, $prevMonth]);
$branchData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .kpi-manager { display: grid; grid-template-columns: 1fr 350px; gap: 20px; align-items: start; font-family: 'Inter', sans-serif; }
    
    /* Таблица */
    .plans-card { background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; }
    .plan-table { width: 100%; border-collapse: collapse; }
    .plan-table th { padding: 12px 15px; text-align: left; font-size: 9px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.08); }
    .plan-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    .plan-val { font-weight: 800; color: #b866ff; }
    .fact-prev { font-size: 11px; color: rgba(255,255,255,0.3); }

    /* Форма */
    .form-sticky { position: sticky; top: 20px; background: rgba(255,255,255,0.02); border: 1px solid rgba(120,90,255,0.2); border-radius: 20px; padding: 20px; }
    .st-input { width: 100%; height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; margin-bottom: 15px; }
    .st-input:focus { border-color: #785aff; }

    .btn-save { width: 100%; height: 40px; background: #785aff; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
    .btn-edit-mini { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11px; }
</style>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="margin:0; font-size: 22px; font-weight: 900;">🎯 Планирование KPI</h1>
        <p style="margin:0; font-size: 13px; opacity: 0.4;">Установка целей на <?= h(date('F Y', strtotime($monthDate))) ?></p>
    </div>
    <form method="get">
        <input type="hidden" name="page" value="kpi_plans">
        <input type="month" name="month" class="st-input" style="margin-bottom:0; width: 160px;" value="<?= h($month) ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="kpi-manager">
    <div class="plans-card">
        <table class="plan-table">
            <thead>
                <tr>
                    <th>Филиал</th>
                    <th>Факт (Прошлый мес.)</th>
                    <th>План (Текущий)</th>
                    <th style="text-align: right;">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branchData as $b): 
                    $curPlan = (float)$b['current_plan'];
                    $prevFact = (float)$b['prev_fact'];
                ?>
                <tr>
                    <td><b><?= h($b['name']) ?></b></td>
                    <td>
                        <div class="fact-prev"><?= number_format($prevFact, 0, '.', ' ') ?> L</div>
                    </td>
                    <td>
                        <?php if ($curPlan > 0): ?>
                            <span class="plan-val"><?= number_format($curPlan, 0, '.', ' ') ?> L</span>
                        <?php else: ?>
                            <span style="opacity: 0.2; font-size: 11px;">Не установлен</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <button class="btn-edit-mini" onclick="fillPlan(<?= $b['id'] ?>,'<?= h($b['name']) ?>','<?= $curPlan ?>')">ИЗМЕНИТЬ</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-sticky">
        <h3 id="formTitle" style="margin:0 0 15px 0; font-size: 16px;">Установить план</h3>
        <form method="post" action="/admin/actions/kpi_plan_save.php">
            <input type="hidden" name="month" value="<?= h($month) ?>">
            
            <label style="font-size: 9px; opacity: 0.4; text-transform: uppercase; display: block; margin-bottom: 5px;">Филиал</label>
            <select name="branch_id" id="branch_id" class="st-input" required>
                <option value="">Выберите...</option>
                <?php foreach ($branchData as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label style="font-size: 9px; opacity: 0.4; text-transform: uppercase; display: block; margin-bottom: 5px;">Сумма плана (L)</label>
            <input type="number" name="plan_amount" id="plan_amount" class="st-input" required placeholder="0">

            <button type="submit" class="btn-save">💾 СОХРАНИТЬ</button>
        </form>

        <div style="margin-top: 20px; font-size: 11px; opacity: 0.4; line-height: 1.4;">
            Планы влияют на выплату бонусов. Рекомендуется ставить план не ниже факта за прошлый месяц.
        </div>
    </div>
</div>

<script>
function fillPlan(bid, name, amount) {
    document.getElementById('branch_id').value = bid;
    document.getElementById('plan_amount').value = amount > 0 ? amount : '';
    document.getElementById('formTitle').innerHTML = '✏️ План: ' + name;
    document.getElementById('plan_amount').focus();
}
</script>