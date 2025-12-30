<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();
$userId = (int)$user['id'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ───────── ПЕРИОД ───────── */
$month = $_GET['month'] ?? date('Y-m');
$from  = $month . '-01 00:00:00';
$to    = date('Y-m-t 23:59:59', strtotime($from));
$daysInMonth = (int)date('t', strtotime($from));

/* ───────── УРОВНИ ───────── */
$levels = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_level_%'");
foreach ($stmt as $r) {
    $min = (int)str_replace('kpi_level_', '', $r['skey']);
    $name = trim($r['svalue']);
    if ($name !== '') $levels[] = ['min'=>$min,'name'=>$name];
}
if (!$levels) {
    $levels = [
        ['name'=>'Стажёр','min'=>0], ['name'=>'Новичок','min'=>5],
        ['name'=>'Уверенный','min'=>10], ['name'=>'Профессионал','min'=>15],
        ['name'=>'Эксперт','min'=>20], ['name'=>'Лидер','min'=>30],
    ];
}
usort($levels, fn($a,$b)=>$a['min']<=>$b['min']);

function getLevel(float $percent, array $levels): string {
    $current = $levels[0]['name'];
    foreach ($levels as $lvl) {
        if ($percent >= $lvl['min']) $current = $lvl['name'];
    }
    return $current;
}

/* ───────── ФИЛИАЛЫ ───────── */
$stmt = $pdo->prepare("
    SELECT DISTINCT b.id, b.name
    FROM sales s
    JOIN branches b ON b.id = s.branch_id
    WHERE s.user_id = ? AND s.created_at BETWEEN ? AND ?
    ORDER BY b.name
");
$stmt->execute([$userId,$from,$to]);
$branches = $stmt->fetchAll();
?>

<style>
    .kpi-container { font-family: 'Inter', sans-serif; max-width: 900px; margin: 0 auto; color: #fff; }
    
    .kpi-header-card {
        display: flex; justify-content: space-between; align-items: center;
        padding: 20px; background: rgba(255,255,255,0.03); border-radius: 16px; margin-bottom: 20px;
    }

    .branch-card {
        background: rgba(255,255,255,0.03); border-radius: 20px; padding: 20px; margin-bottom: 20px;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .branch-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

    /* KPI Сетка */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
    
    .kpi-box {
        background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
        padding: 15px; border-radius: 14px; text-align: center;
    }
    .kpi-label { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
    .kpi-value { font-size: 20px; font-weight: 700; color: #fff; }

    /* Прогресс вклада */
    .contribution-area { margin: 20px 0; }
    .progress-bg { height: 8px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin: 8px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #785aff, #b866ff); border-radius: 10px; transition: 1s ease; }
    
    .level-badge {
        display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 30px;
        background: rgba(120,90,255,0.15); border: 1px solid rgba(120,90,255,0.3);
        font-size: 12px; font-weight: 600; color: #a38cff; margin-top: 5px;
    }

    /* График */
    .chart-container { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 14px; margin-top: 20px; }
    .chart-title { font-size: 12px; color: rgba(255,255,255,0.3); margin-bottom: 15px; text-align: center; }
    .chart-bars { display: flex; align-items: flex-end; gap: 4px; height: 100px; padding-bottom: 20px; }
    .bar-item { flex: 1; background: rgba(120,90,255,0.3); border-radius: 4px 4px 2px 2px; position: relative; min-width: 8px; }
    .bar-item:hover { background: #785aff; }
    .bar-item span { 
        position: absolute; bottom: -18px; left: 50%; transform: translateX(-50%);
        font-size: 9px; color: rgba(255,255,255,0.3);
    }

    input[type="month"] {
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        color: #fff; padding: 8px 12px; border-radius: 10px; outline: none;
    }
</style>

<div class="kpi-container">
    <div class="kpi-header-card">
        <h2 style="margin:0; font-weight:500;">📊 KPI Аналитика</h2>
        <form method="get" style="display:flex;gap:10px">
            <input type="hidden" name="page" value="kpi">
            <input type="month" name="month" value="<?= h($month) ?>">
            <button class="btn" style="padding: 8px 16px;">🔍</button>
        </form>
    </div>

    <?php if (!$branches): ?>
        <div class="card" style="text-align:center; padding:40px; color:rgba(255,255,255,0.3);">
            За выбранный период данных не найдено
        </div>
    <?php endif; ?>

    <?php foreach ($branches as $br): ?>
        <?php
        // МОИ ПРОДАЖИ
        $stmt = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_amount),0) s, COALESCE(AVG(total_amount),0) a FROM sales WHERE user_id=? AND branch_id=? AND created_at BETWEEN ? AND ?");
        $stmt->execute([$userId,$br['id'],$from,$to]);
        $me = $stmt->fetch();

        // ФАКТ ФИЛИАЛА
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id=? AND created_at BETWEEN ? AND ?");
        $stmt->execute([$br['id'],$from,$to]);
        $branchFact = (float)$stmt->fetchColumn();

        $mySum = (float)$me['s'];
        $percent = $branchFact>0 ? ($mySum/$branchFact)*100 : 0;
        $level = getLevel($percent,$levels);

        // ДАННЫЕ ДЛЯ ГРАФИКА
        $daily = array_fill(1,$daysInMonth,0);
        $stmt = $pdo->prepare("SELECT DAY(created_at) d, SUM(total_amount) s FROM sales WHERE user_id=? AND branch_id=? AND created_at BETWEEN ? AND ? GROUP BY DAY(created_at)");
        $stmt->execute([$userId,$br['id'],$from,$to]);
        foreach ($stmt->fetchAll() as $r) { $daily[(int)$r['d']] = (float)$r['s']; }
        $max = max($daily) ?: 1;
        ?>

        <div class="branch-card">
            <div class="branch-title">
                <span>🏬</span> <?= h($br['name']) ?>
            </div>

            <div class="kpi-grid">
                <div class="kpi-box">
                    <div class="kpi-label">Продажи</div>
                    <div class="kpi-value"><?= number_format($mySum, 0, '.', ' ') ?> ₽</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-label">Чеки</div>
                    <div class="kpi-value"><?= (int)$me['c'] ?></div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-label">Средний чек</div>
                    <div class="kpi-value"><?= number_format($me['a'], 0, '.', ' ') ?> ₽</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-label">Ваш вклад</div>
                    <div class="kpi-value"><?= number_format($percent, 1) ?>%</div>
                </div>
            </div>

            <div class="contribution-area">
                <div style="display:flex; justify-content:space-between; font-size:12px; color:rgba(255,255,255,0.4);">
                    <span>Уровень мастерства</span>
                    <span>Доля в филиале: <?= number_format($percent, 1) ?>%</span>
                </div>
                <div class="progress-bg">
                    <div class="progress-fill" style="width: <?= min($percent, 100) ?>%"></div>
                </div>
                <div class="level-badge">✨ <?= h($level) ?></div>
            </div>

            <div class="chart-container">
                <div class="chart-title">Активность продаж по дням</div>
                <div class="chart-bars">
                    <?php foreach ($daily as $d=>$sum): 
                        $h = ($sum/$max)*100;
                    ?>
                        <div class="bar-item" style="height:<?= max($h, 5) ?>%" title="<?= $d ?> число: <?= number_format($sum,0) ?> ₽">
                            <span><?= $d ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
