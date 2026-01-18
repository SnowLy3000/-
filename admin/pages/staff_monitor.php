<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
// Проверка прав: только те, у кого есть доступ к мониторингу
require_role('staff_monitor'); 

// 1. ПОЛУЧАЕМ ТЕКУЩИЙ СТАТУС (КТО ОНЛАЙН)
$stmt = $pdo->query("
    SELECT id, first_name, last_name, last_seen, role_name,
    TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
    FROM users 
    WHERE role_name != 'Owner' 
    ORDER BY last_seen DESC
");
$staff = $stmt->fetchAll();

// 2. ПОЛУЧАЕМ ИСТОРИЮ ПОСЛЕДНИХ 20 ДЕЙСТВИЙ (ВХОДЫ/ВЫХОДЫ)
$logs = $pdo->query("
    SELECT l.*, u.first_name, u.last_name 
    FROM user_sessions_log l
    JOIN users u ON u.id = l.user_id
    WHERE l.action_type IN ('login', 'logout')
    ORDER BY l.created_at DESC
    LIMIT 20
")->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .monitor-container { font-family: 'Inter', sans-serif; color: #fff; }
    
    /* СЕТКА СТАТУСОВ */
    .monitor-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
        gap: 15px; 
        margin-bottom: 40px; 
    }
    
    .user-card { 
        background: rgba(255,255,255,0.02); 
        border: 1px solid rgba(255,255,255,0.05); 
        padding: 18px; 
        border-radius: 20px; 
        display: flex; 
        align-items: center; 
        gap: 15px;
        transition: 0.3s;
    }
    .user-card:hover { background: rgba(255,255,255,0.04); transform: translateY(-2px); }

    .indicator { width: 10px; height: 10px; border-radius: 50%; position: relative; }
    .indicator.online { background: #00c851; box-shadow: 0 0 12px rgba(0, 200, 81, 0.5); }
    .indicator.online::after {
        content: ""; position: absolute; width: 100%; height: 100%; 
        border-radius: 50%; background: #00c851; opacity: 0.4;
        animation: pulse 2s infinite;
    }
    .indicator.offline { background: #444; }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 0.4; }
        70% { transform: scale(2.5); opacity: 0; }
        100% { transform: scale(1); opacity: 0; }
    }
    
    /* ТАБЛИЦА ЛОГОВ */
    .log-section { background: rgba(0,0,0,0.2); border-radius: 24px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
    .log-table { width: 100%; border-collapse: collapse; }
    .log-table th { 
        text-align: left; padding: 15px 20px; font-size: 10px; 
        text-transform: uppercase; color: rgba(255,255,255,0.3); letter-spacing: 1px;
        background: rgba(255,255,255,0.02);
    }
    .log-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }
    
    .badge-log { padding: 5px 10px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; }
    .badge-login { background: rgba(0, 200, 81, 0.1); color: #00c851; }
    .badge-logout { background: rgba(255, 68, 68, 0.1); color: #ff4444; }

    .ip-box { font-family: 'JetBrains Mono', monospace; font-size: 11px; opacity: 0.4; }
</style>

<div class="monitor-container">
    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 24px; font-weight: 900; margin: 0;">🛰️ Live-мониторинг</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.4; font-size: 14px;">Статус персонала в режиме реального времени</p>
        </div>
        <div style="font-size: 12px; opacity: 0.3;">Обновлено: <?= date('H:i:s') ?></div>
    </div>

    <div class="monitor-grid">
        <?php foreach ($staff as $s): 
            $isOnline = ($s['seconds_ago'] !== null && $s['seconds_ago'] <= 150); 
        ?>
        <div class="user-card">
            <div class="indicator <?= $isOnline ? 'online' : 'offline' ?>"></div>
            <div style="flex: 1;">
                <div style="font-weight: 700; font-size: 14px;"><?= h($s['last_name'] . ' ' . $s['first_name']) ?></div>
                <div style="font-size: 10px; opacity: 0.3; text-transform: uppercase; font-weight: 800;"><?= h($s['role_name']) ?></div>
            </div>
            <div style="text-align: right;">
                <?php if($isOnline): ?>
                    <span style="font-size: 10px; color: #00c851; font-weight: 800;">ONLINE</span>
                <?php else: ?>
                    <span style="font-size: 10px; opacity: 0.4;">БЫЛ: <?= date('H:i', strtotime($s['last_seen'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-bottom: 20px; margin-top: 20px;">
        <h2 style="font-size: 18px; font-weight: 800; margin: 0;">📋 Активность сессий</h2>
    </div>

    <div class="log-section">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Действие</th>
                    <th>Время события</th>
                    <th>IP / Устройство</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td>
                        <div style="font-weight: 700;"><?= h($l['last_name'] . ' ' . $l['first_name']) ?></div>
                        <div style="font-size: 10px; opacity: 0.3;">ID: <?= $l['user_id'] ?></div>
                    </td>
                    <td>
                        <span class="badge-log <?= $l['action_type'] === 'login' ? 'badge-login' : 'badge-logout' ?>">
                            <?= $l['action_type'] === 'login' ? '● Вход' : '○ Выход' ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 600;"><?= date('H:i:s', strtotime($l['created_at'])) ?></div>
                        <div style="font-size: 10px; opacity: 0.4;"><?= date('d.m.Y', strtotime($l['created_at'])) ?></div>
                    </td>
                    <td>
                        <div class="ip-box"><?= $l['ip_address'] ?></div>
                        <div style="font-size: 9px; opacity: 0.2; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= h($l['user_agent']) ?>">
                            <?= h($l['user_agent']) ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?>
                    <tr><td colspan="4" style="text-align:center; padding: 50px; opacity: 0.2;">История действий пуста</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>