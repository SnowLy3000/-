<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Фильтры
$branch_id = (int)($_GET['branch_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = "WHERE DATE(r.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($branch_id > 0) {
    $where .= " AND s.branch_id = ?";
    $params[] = $branch_id;
}

// 1. Статистика для дашборда
$stats = $pdo->prepare("
    SELECT 
        COUNT(r.id) as total_count,
        SUM(s.total_amount) as total_sum,
        SUM(CASE WHEN r.reason = 'defect' THEN 1 ELSE 0 END) as defect_count
    FROM returns r
    JOIN sales s ON r.sale_id = s.id
    $where
");
$stats->execute($params);
$totals = $stats->fetch();

$defect_percent = $totals['total_count'] > 0 ? round(($totals['defect_count'] / $totals['total_count']) * 100, 1) : 0;

// 2. Список возвратов
$stmt = $pdo->prepare("
    SELECT r.*, s.total_amount, b.name as branch_name, u.first_name as staff_name,
    (SELECT COUNT(*) FROM return_photos WHERE return_id = r.id) as photo_count
    FROM returns r
    JOIN sales s ON r.sale_id = s.id
    JOIN branches b ON s.branch_id = b.id
    JOIN users u ON r.staff_id = u.id
    $where
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
?>

<style>
    .returns-container { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    
    /* КАРТОЧКИ KPI */
    .ret-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .ret-card { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); 
        padding: 20px; border-radius: 20px; position: relative; overflow: hidden;
    }
    .ret-card span { display: block; font-size: 9px; text-transform: uppercase; opacity: 0.4; font-weight: 800; letter-spacing: 1px; }
    .ret-card b { font-size: 22px; display: block; margin-top: 5px; }
    .ret-card i { position: absolute; right: -10px; bottom: -10px; font-size: 50px; opacity: 0.05; font-style: normal; }

    /* ФИЛЬТР */
    .filter-stripe { 
        background: rgba(0,0,0,0.2); padding: 15px 25px; border-radius: 18px; 
        margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; border: 1px solid #222;
    }
    .f-item { display: flex; flex-direction: column; gap: 5px; flex: 1; }
    .f-item label { font-size: 9px; font-weight: 800; opacity: 0.3; text-transform: uppercase; }
    .st-input { height: 38px; background: #000; border: 1px solid #333; border-radius: 10px; color: #fff; padding: 0 12px; font-size: 13px; outline: none; }
    .btn-apply { height: 38px; background: #785aff; color: #fff; border: none; border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; }

    /* ТАБЛИЦА */
    .ret-table-box { background: #0f0f13; border-radius: 24px; border: 1px solid #1f1f23; overflow: hidden; }
    .ret-table { width: 100%; border-collapse: collapse; }
    .ret-table th { padding: 12px 15px; text-align: left; font-size: 9px; text-transform: uppercase; color: #41414c; background: #16161a; }
    .ret-table td { padding: 12px 15px; border-bottom: 1px solid #16161a; font-size: 13px; }
    
    .badge-reason { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; }
    .br-defect { background: rgba(255, 75, 43, 0.1); color: #ff4b2b; }
    .br-other { background: rgba(120, 90, 255, 0.1); color: #785aff; }
    
    .photo-tag { color: #7CFF6B; font-weight: 800; font-size: 11px; display: flex; align-items: center; gap: 4px; }
</style>

<div class="returns-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 24px; font-weight: 900;">🛡️ Контроль возвратов</h1>
            <p style="margin:0; font-size: 13px; opacity: 0.4;">Мониторинг брака и финансовых корректировок</p>
        </div>
    </div>

    <div class="ret-grid">
        <div class="ret-card"><span>Сумма возвратов</span><b style="color:#ff4b2b;"><?= number_format($totals['total_sum'] ?? 0, 0, '.', ' ') ?> L</b><i>💸</i></div>
        <div class="ret-card"><span>Кол-во операций</span><b><?= $totals['total_count'] ?? 0 ?></b><i>📄</i></div>
        <div class="ret-card"><span>Доля брака</span><b style="color:<?= $defect_percent > 15 ? '#ff4b2b' : '#7CFF6B' ?>;"><?= $defect_percent ?>%</b><i>❌</i></div>
    </div>

    <form class="filter-stripe" method="GET">
        <input type="hidden" name="page" value="returns_control">
        <div class="f-item">
            <label>От</label>
            <input type="date" name="date_from" value="<?= h($date_from) ?>" class="st-input">
        </div>
        <div class="f-item">
            <label>До</label>
            <input type="date" name="date_to" value="<?= h($date_to) ?>" class="st-input">
        </div>
        <div class="f-item" style="flex:1.5;">
            <label>Филиал</label>
            <select name="branch_id" class="st-input">
                <option value="0">Все локации</option>
                <?php foreach($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-apply">Применить</button>
    </form>

    <div class="ret-table-box">
        <table class="ret-table">
            <thead>
                <tr>
                    <th>Дата / Время</th>
                    <th>Филиал / Сотрудник</th>
                    <th>Продажа</th>
                    <th>Причина</th>
                    <th style="text-align: right;">Сумма</th>
                    <th style="text-align: center;">Фото</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$list): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; opacity: 0.2;">Возвратов за период не найдено</td></tr>
                <?php endif; ?>

                <?php foreach ($list as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight: 700;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></div>
                        <div style="font-size: 10px; opacity: 0.4;"><?= date('H:i', strtotime($r['created_at'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 600;"><?= h($r['branch_name']) ?></div>
                        <div style="font-size: 10px; opacity: 0.4;">👤 <?= h($r['staff_name']) ?></div>
                    </td>
                    <td>
                        <a href="index.php?page=sale_view&id=<?= $r['sale_id'] ?>" style="color: #785aff; text-decoration: none; font-weight: 800;">#<?= $r['sale_id'] ?></a>
                        <?php if($r['return_1c_id']): ?>
                            <div style="font-size: 9px; color: #4ade80;">1C: <?= h($r['return_1c_id']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-reason <?= $r['reason'] == 'defect' ? 'br-defect' : 'br-other' ?>">
                            <?= $r['reason'] == 'defect' ? '❌ БРАК' : '🔄 ВОЗВРАТ' ?>
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 900; font-size: 15px;">
                        <?= number_format($r['total_amount'], 0, '.', ' ') ?> L
                    </td>
                    <td style="text-align: center;">
                        <?php if($r['photo_count'] > 0): ?>
                            <span class="photo-tag">📸 <?= $r['photo_count'] ?></span>
                        <?php else: ?>
                            <span style="opacity: 0.1;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>