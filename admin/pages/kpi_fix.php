<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_kpi_plans'); 

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* --- НАСТРОЙКИ --- */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_bonus_%'");
foreach ($stmt as $row) { $settings[$row['skey']] = (float)$row['svalue']; }

$branchId = (int)($_GET['branch_id'] ?? 0);
$month    = $_GET['month'] ?? date('Y-m');

$from = $month . '-01 00:00:00';
$to   = date('Y-m-t 23:59:59', strtotime($from));
$branches = $pdo->query("SELECT id,name FROM branches ORDER BY name")->fetchAll();

/* --- ПРОВЕРКА ФИКСАЦИИ --- */
$isFixed = false;
$fixedData = null;
if ($branchId) {
    $stmt = $pdo->prepare("SELECT * FROM kpi_facts WHERE branch_id = ? AND DATE_FORMAT(month_date,'%Y-%m') = ?");
    $stmt->execute([$branchId, $month]);
    $fixedData = $stmt->fetch();
    $isFixed = (bool)$fixedData;
}

/* --- РАСЧЕТ ТЕКУЩИХ ЦИФР --- */
$plan = 0; $fact = 0; $kpi = 0; $promoFact = 0;
if ($branchId) {
    // План
    $stmt = $pdo->prepare("SELECT plan_amount FROM kpi_plans WHERE branch_id = ? AND DATE_FORMAT(month_date,'%Y-%m') = ?");
    $stmt->execute([$branchId, $month]);
    $plan = (float)($stmt->fetchColumn() ?: 0);

    // Общая выручка
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id = ? AND created_at BETWEEN ? AND ? AND is_returned = 0");
    $stmt->execute([$branchId, $from, $to]);
    $fact = (float)$stmt->fetchColumn();

    // ВЫРУЧКА ПО АКЦИЯМ (Новое!)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(s.total_amount),0) 
        FROM sales s
        WHERE s.branch_id = ? AND s.created_at BETWEEN ? AND ? AND s.is_returned = 0
        AND EXISTS (
            SELECT 1 FROM sale_items si 
            JOIN product_promotions pr ON pr.product_name = si.product_name 
            WHERE si.sale_id = s.id AND DATE(s.created_at) BETWEEN pr.start_date AND pr.end_date
        )
    ");
    $stmt->execute([$branchId, $from, $to]);
    $promoFact = (float)$stmt->fetchColumn();

    $kpi = ($plan > 0) ? ($fact / $plan) * 100 : 0;
}

$bonusPercent = 0;
if ($kpi >= 130) $bonusPercent = $settings['kpi_bonus_130'] ?? 30;
elseif ($kpi >= 120) $bonusPercent = $settings['kpi_bonus_120'] ?? 20;
elseif ($kpi >= 110) $bonusPercent = $settings['kpi_bonus_110'] ?? 10;
elseif ($kpi >= 100) $bonusPercent = $settings['kpi_bonus_100'] ?? 0;

$bonusAmount = $fact * ($bonusPercent / 100);
$promoShare = ($fact > 0) ? ($promoFact / $fact) * 100 : 0;
?>

<style>
    .fix-container { max-width: 900px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; }
    
    /* Компактный фильтр */
    .filter-bar { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); 
        padding: 15px 25px; border-radius: 20px; display: flex; gap: 12px; align-items: flex-end; margin-bottom: 25px;
    }
    .st-input { height: 38px; background: #0b0b12; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    
    /* Сетка показателей */
    .data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 25px; }
    .data-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 18px; text-align: center; }
    .data-card label { display: block; font-size: 9px; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 5px; font-weight: 800; }
    .data-card b { font-size: 20px; font-weight: 900; }

    /* Карточка статуса */
    .status-card { 
        background: rgba(120, 90, 255, 0.02); border: 1px dashed rgba(120, 90, 255, 0.2); 
        border-radius: 24px; padding: 30px; text-align: center; 
    }
    .btn-fix { 
        width: 100%; height: 50px; background: #2ecc71; color: #fff; border: none; 
        border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.2s; margin-top: 20px;
    }
    .btn-fix:hover { background: #27ae60; transform: translateY(-2px); }
</style>

<div class="fix-container">
    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; background: rgba(120,90,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">🔒</div>
        <h1 style="margin:0; font-size: 22px; font-weight: 800;">Фиксация периода</h1>
    </div>

    <form class="filter-bar">
        <input type="hidden" name="page" value="kpi_fix">
        <div style="flex: 2; display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 9px; opacity: 0.4;">ФИЛИАЛ</label>
            <select name="branch_id" class="st-input" required style="width: 100%;">
                <option value="">Выберите локацию...</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1; display: flex; flex-direction: column; gap: 5px;">
            <label style="font-size: 9px; opacity: 0.4;">МЕСЯЦ</label>
            <input type="month" name="month" class="st-input" value="<?= h($month) ?>">
        </div>
        <button class="btn" style="height: 38px; background: #785aff; color: #fff; border: none; padding: 0 20px; border-radius: 10px; font-weight: 700; cursor: pointer;">ПРОВЕРИТЬ</button>
    </form>

    <?php if ($branchId): ?>
        <div class="data-grid">
            <div class="data-card"><label>План</label><b><?= number_format($plan, 0, '.', ' ') ?> L</b></div>
            <div class="data-card"><label>Факт</label><b><?= number_format($fact, 0, '.', ' ') ?> L</b></div>
            <div class="data-card"><label>% Выполнения</label><b style="color: #b866ff;"><?= number_format($kpi, 1) ?>%</b></div>
            <div class="data-card" style="border-color: rgba(255, 75, 43, 0.2);">
                <label style="color: #ff4b2b;">Доля Акций</label>
                <b style="color: #ff4b2b;"><?= number_format($promoShare, 1) ?>%</b>
            </div>
            <div class="data-card" style="border-color: rgba(46, 204, 113, 0.2);">
                <label style="color: #2ecc71;">Бонус фонд</label>
                <b style="color: #2ecc71;"><?= number_format($bonusAmount, 0, '.', ' ') ?> L</b>
            </div>
        </div>

<?php if ($isFixed): ?>
    <div class="status-card" style="border-color: #2ecc71; background: rgba(46, 204, 113, 0.02);">
        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
        <h3 style="margin:0; color: #2ecc71;">Месяц зафиксирован</h3>
        <p style="font-size: 13px; opacity: 0.5; margin-top: 10px;">
            <?php 
                // Проверяем разные возможные ключи даты в базе
                $dateValue = $fixedData['created_at'] ?? $fixedData['fixed_at'] ?? $fixedData['month_date'] ?? null;
                $formattedDate = $dateValue ? date('d.m.Y в H:i', strtotime($dateValue)) : 'Дата не указана';
            ?>
            Запись создана: <b><?= $formattedDate ?></b><br>
            Данные защищены от изменений и корректировок.
        </p>
    </div>
<?php else: ?>
            <div class="status-card">
                <div style="font-size: 40px; margin-bottom: 10px;">⏳</div>
                <h3 style="margin:0;">Ожидание подтверждения</h3>
                <p style="font-size: 13px; opacity: 0.5; margin: 10px auto 0; max-width: 450px;">
                    Внимание: после нажатия на кнопку данные за <b><?= h($month) ?></b> будут сохранены в архив. 
                    Убедитесь, что все возвраты проведены, а продажи соответствуют действительности.
                </p>
                
                <form method="post" action="/admin/actions/kpi_fix_save.php" onsubmit="return confirm('Подтвердить окончательный расчет?')">
                    <input type="hidden" name="branch_id" value="<?= $branchId ?>">
                    <input type="hidden" name="month" value="<?= h($month) ?>">
                    <input type="hidden" name="plan" value="<?= $plan ?>">
                    <input type="hidden" name="fact" value="<?= $fact ?>">
                    <input type="hidden" name="kpi" value="<?= $kpi ?>">
                    <input type="hidden" name="bonus_amount" value="<?= $bonusAmount ?>">
                    <button type="submit" class="btn-fix">🚀 ПОДТВЕРДИТЬ И ЗАКРЫТЬ МЕСЯЦ</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>