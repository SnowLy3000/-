<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// Доступ только для высшего руководства
require_role('manage_kpi_plans'); 

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* --- ЗАГРУЗКА НАСТРОЕК БОНУСОВ --- */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_bonus_%'");
foreach ($stmt as $row) { $settings[$row['skey']] = (float)$row['svalue']; }

$branchId = (int)($_GET['branch_id'] ?? 0);
$month    = $_GET['month'] ?? date('Y-m');

$from = $month . '-01 00:00:00';
$to   = date('Y-m-t 23:59:59', strtotime($from));

/* --- филиалы --- */
$branches = $pdo->query("SELECT id,name FROM branches ORDER BY name")->fetchAll();

/* --- проверка фиксации --- */
$isFixed = false;
$fixedData = null;
if ($branchId) {
    $stmt = $pdo->prepare("SELECT * FROM kpi_facts WHERE branch_id = ? AND DATE_FORMAT(month_date,'%Y-%m') = ?");
    $stmt->execute([$branchId, $month]);
    $fixedData = $stmt->fetch();
    $isFixed = (bool)$fixedData;
}

/* --- расчет текущих цифр --- */
$plan = 0; $fact = 0; $kpi = 0;
if ($branchId) {
    $stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id = ? AND DATE_FORMAT(month_date,'%Y-%m') = ?");
    $stmt->execute([$branchId, $month]);
    $plan = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id = ? AND created_at BETWEEN ? AND ?");
    $stmt->execute([$branchId, $from, $to]);
    $fact = (float)$stmt->fetchColumn();

    $kpi = ($plan > 0) ? ($fact / $plan) * 100 : 0;
}

/* --- Бонусная логика --- */
$bonusPercent = 0;
if ($kpi >= 130) $bonusPercent = $settings['kpi_bonus_130'] ?? 30;
elseif ($kpi >= 120) $bonusPercent = $settings['kpi_bonus_120'] ?? 20;
elseif ($kpi >= 110) $bonusPercent = $settings['kpi_bonus_110'] ?? 10;
elseif ($kpi >= 100) $bonusPercent = $settings['kpi_bonus_100'] ?? 0;

$bonusAmount = $fact * ($bonusPercent / 100);
?>

<style>
    .fix-container { max-width: 900px; margin: 0 auto; }
    .st-input { height: 48px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 15px; color: #fff; outline: none; transition: 0.3s; }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }
    
    .data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
    .data-item { 
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%); 
        padding: 25px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.08); 
        text-align: center;
    }
    .data-item label { display: block; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 10px; font-weight: 800; letter-spacing: 1px; }
    .data-item b { font-size: 24px; color: #fff; font-weight: 900; }

    .lock-card { 
        background: rgba(120, 90, 255, 0.03); border: 2px dashed rgba(120, 90, 255, 0.2); 
        border-radius: 32px; padding: 40px; text-align: center; position: relative;
    }
    .fixed-badge { 
        background: #4ade80; color: #064e3b; padding: 6px 20px; border-radius: 10px; 
        font-weight: 900; font-size: 12px; display: inline-block; margin-bottom: 20px;
        box-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
    }
    .status-icon { font-size: 48px; margin-bottom: 20px; display: block; }
    
    .btn-fix { 
        width: 100%; height: 60px; font-size: 16px; font-weight: 800; margin-top: 25px; 
        background: linear-gradient(90deg, #00c851, #007e33); color: #fff; border: none; 
        border-radius: 18px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(0, 200, 81, 0.2);
    }
    .btn-fix:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0, 200, 81, 0.3); }
</style>

<div class="fix-container">
    <div class="card" style="border-radius: 28px; padding: 35px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin:0; font-size: 28px;">🔒 Фиксация периода</h1>
                <p class="muted" style="margin-top: 5px;">Закрытие финансовой отчетности и подтверждение премий</p>
            </div>
            <form method="get" style="display:flex; gap:12px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="kpi_fix">
                <select name="branch_id" class="st-input" required style="min-width: 220px;">
                    <option value="">Выберите филиал</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="month" name="month" class="st-input" value="<?= h($month) ?>">
                <button class="btn" style="height: 48px; padding: 0 25px;">Проверить итоги</button>
            </form>
        </div>
    </div>

    <?php if ($branchId): ?>
        
        <div class="data-grid">
            <div class="data-item"><label>План на месяц</label><b><?= number_format($plan, 0, '.', ' ') ?> L</b></div>
            <div class="data-item"><label>Итоговая выручка</label><b><?= number_format($fact, 0, '.', ' ') ?> L</b></div>
            <div class="data-item"><label>% Выполнения</label><b style="color: #b866ff;"><?= number_format($kpi, 1) ?>%</b></div>
            <div class="data-item"><label>Бонусный фонд</label><b style="color: #4ade80;"><?= number_format($bonusAmount, 0, '.', ' ') ?> L</b></div>
        </div>

        <?php if ($isFixed): ?>
            <div class="lock-card" style="border-style: solid; border-color: rgba(74, 222, 128, 0.3); background: rgba(74, 222, 128, 0.02);">
                <span class="status-icon">🛡️</span>
                <div class="fixed-badge">ЗАРЕГИСТРИРОВАНО</div>
                <div style="font-size: 22px; font-weight: 900; color: #fff;">Месяц успешно закрыт</div>
                <div class="muted" style="margin-top: 15px; font-size: 14px;">
                    Данные за <b><?= h(date('F Y', strtotime($monthDate))) ?></b> заблокированы от изменений.<br>
                    Фиксация проведена: <?= date('d.m.Y в H:i', strtotime($fixedData['created_at'] ?? $fixedData['fixed_at'])) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="lock-card">
                <span class="status-icon">⚠️</span>
                <h3 style="margin-top:0; font-size: 22px; color: #fff;">Ожидание фиксации</h3>
                <p class="muted" style="max-width: 500px; margin: 15px auto; font-size: 14px; line-height: 1.6;">
                    Внимательно проверьте цифры выше. После нажатия на кнопку, результаты этого филиала за текущий месяц будут сохранены в историю и <b>защищены от корректировок</b>.
                </p>
                
                <form method="post" action="/admin/actions/kpi_fix_save.php" onsubmit="return confirm('Вы уверены? Это действие создаст неизменяемую финансовую запись.')">
                    <input type="hidden" name="branch_id" value="<?= $branchId ?>">
                    <input type="hidden" name="month" value="<?= h($month) ?>">
                    <input type="hidden" name="plan" value="<?= $plan ?>">
                    <input type="hidden" name="fact" value="<?= $fact ?>">
                    <input type="hidden" name="kpi" value="<?= $kpi ?>">
                    <input type="hidden" name="bonus_percent" value="<?= $bonusPercent ?>">
                    <input type="hidden" name="bonus_amount" value="<?= $bonusAmount ?>">

                    <button type="submit" class="btn-fix">
                        🚀 ЗАФИКСИРОВАТЬ И ЗАКРЫТЬ МЕСЯЦ
                    </button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
