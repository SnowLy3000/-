<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/settings.php';

// 1. ПРОВЕРКА АВТОРИЗАЦИИ
require_auth();

// 2. ОГРАНИЧЕНИЕ ДОСТУПА ПО РОЛЯМ
// Только Админ, Владелец или Маркетолог могут видеть этот журнал
if (!has_role('Admin') && !has_role('Owner') && !has_role('Marketing')) {
    echo '
    <div style="padding: 100px 20px; text-align: center; font-family: \'Inter\', sans-serif; color: #fff; background: #0b0f1a; min-height: 100vh;">
        <div style="font-size: 80px; margin-bottom: 20px;">🚫</div>
        <h2 style="font-size: 24px; font-weight: 800; color: #ff4444;">Доступ ограничен</h2>
        <p style="opacity: 0.6; max-width: 400px; margin: 10px auto 30px;">У вас недостаточно прав для просмотра финансовой отчетности и журнала дисциплины.</p>
        <a href="/cabinet/index.php" style="display: inline-block; background: #785aff; color: #fff; padding: 12px 25px; border-radius: 12px; text-decoration: none; font-weight: 700; transition: 0.3s;">Вернуться в кабинет</a>
    </div>';
    exit;
}

// 3. ПОЛУЧАЕМ НАСТРОЙКИ ШТРАФА
$fine_enabled = setting('late_fine_enabled', '0');
$fine_per_min = (float)setting('late_fine_per_minute', '0');

// 4. ПОЛУЧАЕМ ДАННЫЕ ОБ ОПОЗДАНИЯХ
$stmt = $pdo->query("
    SELECT 
        s.checkin_at, 
        s.late_minutes, 
        u.first_name, u.last_name, 
        b.name as branch_name,
        bs.opening_time
    FROM shift_sessions s
    JOIN users u ON u.id = s.user_id
    JOIN branches b ON b.id = s.branch_id
    LEFT JOIN branch_schedules bs ON bs.branch_id = b.id
    WHERE s.late_minutes > 0
    ORDER BY s.checkin_at DESC
    LIMIT 50
");
$lates = $stmt->fetchAll();

// 5. СТАТИСТИКА
$total_late_count = count($lates);
$total_late_minutes = 0;
foreach($lates as $l) $total_late_minutes += $l['late_minutes'];
$total_fines = $total_late_minutes * $fine_per_min;
?>

<style>
    .late-container { font-family: 'Inter', sans-serif; color: #fff; }
    
    .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { 
        background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); 
        padding: 25px; border-radius: 24px; position: relative; overflow: hidden;
    }
    .stat-card label { display: block; font-size: 11px; text-transform: uppercase; opacity: 0.4; letter-spacing: 1px; margin-bottom: 10px; font-weight: 800; }
    .stat-card b { font-size: 28px; font-weight: 900; }
    .stat-card .icon { position: absolute; right: -10px; bottom: -10px; font-size: 60px; opacity: 0.05; transform: rotate(-15deg); }

    .table-card { background: rgba(255, 255, 255, 0.02); border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.05); overflow: hidden; }
    .late-table { width: 100%; border-collapse: collapse; }
    .late-table th { text-align: left; padding: 20px; font-size: 11px; text-transform: uppercase; opacity: 0.3; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .late-table td { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.03); transition: 0.3s; }
    .late-table tr:hover td { background: rgba(255, 68, 68, 0.03); }

    .user-info b { display: block; font-size: 15px; }
    .user-info span { font-size: 12px; opacity: 0.5; }

    .late-badge { 
        display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 12px; 
        font-weight: 800; font-size: 13px; font-family: 'Monaco', monospace;
    }
    .late-small { background: rgba(255, 187, 51, 0.15); color: #ffbb33; } 
    .late-medium { background: rgba(255, 68, 68, 0.15); color: #ff4444; } 
    .late-hard { background: #ff4444; color: #fff; box-shadow: 0 4px 15px rgba(255,68,68,0.3); } 

    .fine-text { font-size: 11px; color: #ff4444; margin-top: 5px; font-weight: 800; }
    .time-box { display: flex; align-items: center; gap: 10px; }
    .time-box .dot { width: 6px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 50%; }
</style>

<div class="late-container">
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 32px; font-weight: 900; margin: 0; letter-spacing: -1px;">⏰ Дисциплина</h1>
        <p style="opacity: 0.5; margin-top: 5px;">Мониторинг опозданий и штрафов</p>
    </div>

    <div class="summary-row">
        <div class="stat-card">
            <label>Нарушений</label>
            <b><?= $total_late_count ?></b>
            <div class="icon">🚫</div>
        </div>
        <div class="stat-card">
            <label>Сумма минут</label>
            <b style="color: #ffbb33;"><?= $total_late_minutes ?> <small style="font-size: 14px;">мин</small></b>
            <div class="icon">⏳</div>
        </div>
        <?php if($fine_enabled === '1'): ?>
        <div class="stat-card">
            <label>Общий штраф</label>
            <b style="color: #ff4444;"><?= number_format($total_fines, 2) ?> <small style="font-size: 14px;">MDL</small></b>
            <div class="icon">💸</div>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <table class="late-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Локация</th>
                    <th>План / Факт</th>
                    <th style="text-align: right;">Итог</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lates as $l): 
                    $m = (int)$l['late_minutes'];
                    $badgeClass = ($m > 30) ? 'late-hard' : (($m > 15) ? 'late-medium' : 'late-small');
                ?>
                <tr>
                    <td>
                        <div class="user-info">
                            <b><?= htmlspecialchars($l['last_name'] . ' ' . $l['first_name']) ?></b>
                            <span><?= date('d.m.Y', strtotime($l['checkin_at'])) ?></span>
                        </div>
                    </td>
                    <td><span style="opacity: 0.8; font-weight: 600;">🏙 <?= htmlspecialchars($l['branch_name']) ?></span></td>
                    <td>
                        <div class="time-box">
                            <span style="opacity: 0.4;"><?= date('H:i', strtotime($l['opening_time'])) ?></span>
                            <div class="dot"></div>
                            <b style="color: #785aff;"><?= date('H:i', strtotime($l['checkin_at'])) ?></b>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <div class="late-badge <?= $badgeClass ?>">+ <?= $m ?> мин</div>
                        <?php if($fine_enabled === '1' && $fine_per_min > 0): ?>
                            <div class="fine-text">- <?= number_format($m * $fine_per_min, 2) ?> MDL</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
