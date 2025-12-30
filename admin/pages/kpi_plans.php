<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();

// Защита прав: только те, кто управляет планами
require_role('manage_kpi_plans');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Настройка текущего месяца */
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('~^\d{4}-\d{2}$~', $month)) $month = date('Y-m');
$monthDate = $month . '-01';

/* Загрузка филиалов */
$stmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Загрузка планов */
$stmt = $pdo->prepare("
    SELECT p.id, p.branch_id, p.month_date, p.plan_amount, b.name AS branch_name
    FROM kpi_plans p
    JOIN branches b ON b.id = p.branch_id
    WHERE p.month_date = ?
    ORDER BY b.name
");
$stmt->execute([$monthDate]);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$planMap = [];
foreach ($plans as $p) $planMap[(int)$p['branch_id']] = $p;
?>

<style>
    .kpi-manager { display: grid; grid-template-columns: 1fr 400px; gap: 30px; align-items: start; }
    
    .st-input { 
        width: 100%; height: 50px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 15px; padding: 0 18px; color: #fff; margin-bottom: 20px; outline: none; font-size: 15px; transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.06); box-shadow: 0 0 15px rgba(120,90,255,0.1); }

    .plan-table { width: 100%; border-collapse: collapse; }
    .plan-table th { padding: 18px; text-align: left; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.08); letter-spacing: 1px; }
    .plan-table td { padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 15px; }
    
    .plan-amount { font-weight: 800; font-size: 17px; color: #b866ff; text-shadow: 0 0 10px rgba(184, 102, 255, 0.2); }
    .plan-unset { color: rgba(255,255,255,0.15); font-style: italic; font-size: 13px; }

    .btn-action { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; cursor: pointer; transition: 0.3s; border: none; text-decoration: none; }
    .btn-edit { background: rgba(120,90,255,0.1); color: #785aff; }
    .btn-edit:hover { background: #785aff; color: #fff; transform: scale(1.1); }
    .btn-del { background: rgba(255,107,107,0.1); color: #ff6b6b; }
    .btn-del:hover { background: #ff6b6b; color: #fff; transform: scale(1.1); }

    .form-sticky { position: sticky; top: 25px; background: linear-gradient(145deg, rgba(120,90,255,0.05) 0%, rgba(255,255,255,0.02) 100%); border: 1px solid rgba(120,90,255,0.2); border-radius: 30px; padding: 35px; }
    
    .month-selector { background: rgba(120,90,255,0.05); border: 1px solid rgba(120,90,255,0.15); border-radius: 15px; padding: 5px 15px; height: 46px; color: #fff; font-weight: 700; cursor: pointer; }

    @media (max-width: 1100px) { .kpi-manager { grid-template-columns: 1fr; } .form-sticky { position: static; } }
</style>

<div class="card" style="margin-bottom: 30px; border-radius: 25px;">
    <div style="display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="margin:0; font-size: 28px;">🎯 Целевые показатели</h1>
            <p class="muted">Установите планы продаж для филиалов на <b><?= h(date('F Y', strtotime($monthDate))) ?></b></p>
        </div>

        <form method="get" style="display: flex; gap: 12px;">
            <input type="hidden" name="page" value="kpi_plans">
            <input type="month" name="month" class="month-selector" value="<?= h($month) ?>" onchange="this.form.submit()">
        </form>
    </div>
</div>

<div class="kpi-manager">
    <div class="card" style="padding: 0; overflow: hidden; border-radius: 25px;">
        <table class="plan-table">
            <thead>
                <tr>
                    <th>Филиал</th>
                    <th>Сумма плана</th>
                    <th style="text-align: right;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): 
                    $bid = (int)$b['id'];
                    $p = $planMap[$bid] ?? null;
                    $planAmount = $p ? (float)$p['plan_amount'] : 0.0;
                ?>
                <tr id="branch-row-<?= $bid ?>">
                    <td><b style="color: #fff;"><?= h($b['name']) ?></b></td>
                    <td>
                        <?php if ($p): ?>
                            <span class="plan-amount"><?= number_format($planAmount, 0, '.', ' ') ?> L</span>
                        <?php else: ?>
                            <span class="plan-unset">Цель не установлена</span>
                        <?php endif; ?>
                    </td>
                    <td style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button class="btn-action btn-edit" onclick="fillPlan(<?= $bid ?>,'<?= h($b['name']) ?>','<?= number_format($planAmount,2,'.','') ?>')" title="Редактировать">
                            ✏️
                        </button>
                        <?php if ($p): ?>
                            <form method="post" action="/admin/actions/kpi_plan_delete.php" onsubmit="return confirm('Удалить этот план?')">
                                <input type="hidden" name="branch_id" value="<?= $bid ?>">
                                <input type="hidden" name="month" value="<?= h($month) ?>">
                                <button class="btn-action btn-del" title="Удалить">✖</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-sticky" id="formCard">
        <h3 id="formTitle" style="margin-top: 0; margin-bottom: 25px; font-size: 20px;">➕ Установить план</h3>
        
        <form method="post" action="/admin/actions/kpi_plan_save.php">
            <input type="hidden" name="month" value="<?= h($month) ?>">

            <label class="muted" style="font-size: 10px; text-transform: uppercase; font-weight: 800; margin-bottom: 10px; display: block;">Торговая точка</label>
            <select class="st-input" name="branch_id" id="branch_id" required>
                <option value="">— Выберите филиал —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="muted" style="font-size: 10px; text-transform: uppercase; font-weight: 800; margin-bottom: 10px; display: block;">Сумма (MDL)</label>
            <input class="st-input" type="number" step="1" min="0" name="plan_amount" id="plan_amount" required placeholder="Напр: 500000">

            <button class="btn" style="width: 100%; padding: 16px; font-weight: 800; background: #785aff; border-radius: 15px; margin-top: 10px;">
                💾 СОХРАНИТЬ ПЛАН
            </button>
            
            <div style="margin-top: 25px; padding: 20px; background: rgba(120,90,255,0.05); border-radius: 15px; border: 1px solid rgba(120,90,255,0.1);">
                <div style="font-size: 13px; color: #b866ff; margin-bottom: 8px; font-weight: 800;">ℹ️ Информация</div>
                <div style="font-size: 12px; line-height: 1.5; color: rgba(255,255,255,0.5);">
                    Планы используются для расчета коэффициентов KPI. Если филиал выполняет план на 100%, сотрудники получают бонусы согласно <a href="?page=kpi_settings" style="color:#785aff;">настройкам сетки</a>.
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function fillPlan(branchId, branchName, planAmount){
    document.getElementById('branch_id').value = String(branchId);
    document.getElementById('plan_amount').value = planAmount > 0 ? planAmount : '';
    document.getElementById('formTitle').innerHTML = '✏️ План: <span style="color:#785aff">' + branchName + '</span>';
    document.getElementById('plan_amount').focus();
    
    // Эффект фокусировки на форме
    const card = document.getElementById('formCard');
    card.style.borderColor = '#785aff';
    card.style.boxShadow = '0 0 30px rgba(120,90,255,0.2)';
    setTimeout(() => { 
        card.style.borderColor = 'rgba(120,90,255,0.2)';
        card.style.boxShadow = 'none';
    }, 1000);
}
</script>
