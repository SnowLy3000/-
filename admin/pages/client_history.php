<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// ПРОВЕРКА ДОСТУПА
require_auth();
if (!can_user('clients')) { // Используем существующее право 'clients' или создай 'client_history'
    echo "
    <div style='padding: 100px 20px; text-align: center; color: #fff; background: #0b0f1a; min-height: 100vh; font-family: \"Inter\", sans-serif;'>
        <div style='font-size: 80px; margin-bottom: 20px;'>🔒</div>
        <h2 style='color: #ff4444; font-weight: 900;'>Доступ закрыт</h2>
        <p style='opacity: 0.6; max-width: 400px; margin: 0 auto 30px;'>Просмотр системного журнала действий доступен только администраторам.</p>
        <a href='?page=clients' style='display: inline-block; background: #785aff; color: #fff; padding: 12px 25px; border-radius: 12px; text-decoration: none; font-weight: bold;'>Вернуться к клиентам</a>
    </div>";
    exit;
}

// Загружаем логи с объединением имен сотрудников
$query = "
    SELECT 
        l.*, 
        u.first_name, u.last_name, 
        c.name as client_name,
        c.phone as client_phone
    FROM client_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN clients c ON l.client_id = c.id
    ORDER BY l.created_at DESC 
    LIMIT 100
";
$logs = $pdo->query($query)->fetchAll();

function getActionStyle($type) {
    switch ($type) {
        case 'add': return ['bg' => 'rgba(46, 204, 113, 0.1)', 'color' => '#2ecc71', 'text' => 'Добавление', 'icon' => '➕'];
        case 'edit': return ['bg' => 'rgba(120, 90, 255, 0.1)', 'color' => '#785aff', 'text' => 'Изменение', 'icon' => '📝'];
        case 'delete': return ['bg' => 'rgba(255, 68, 68, 0.1)', 'color' => '#ff4444', 'text' => 'Удаление', 'icon' => '🗑️'];
        case 'import': return ['bg' => 'rgba(52, 152, 219, 0.1)', 'color' => '#3498db', 'text' => 'Импорт', 'icon' => '📥'];
        default: return ['bg' => 'rgba(255,255,255,0.05)', 'color' => '#fff', 'text' => 'Действие', 'icon' => '⚙️'];
    }
}
?>

<style>
    .h-page { font-family: 'Inter', sans-serif; color: #fff; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .h-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .h-top h1 { margin: 0; font-size: 32px; font-weight: 900; letter-spacing: -1px; }
    .h-top p { margin: 5px 0 0 0; opacity: 0.5; font-size: 14px; }

    .btn-back { 
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
        color: #fff; padding: 12px 20px; border-radius: 14px; text-decoration: none; 
        font-size: 14px; font-weight: 700; transition: 0.3s;
    }
    .btn-back:hover { background: rgba(255,255,255,0.1); transform: translateX(-5px); }

    .h-list { display: flex; flex-direction: column; gap: 12px; }
    .h-item { 
        background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); 
        border-radius: 24px; padding: 20px; display: flex; align-items: center; 
        gap: 25px; transition: 0.2s;
    }
    .h-item:hover { background: rgba(255, 255, 255, 0.03); border-color: rgba(120, 90, 255, 0.2); }

    .h-time { min-width: 100px; text-align: center; border-right: 1px solid rgba(255,255,255,0.08); padding-right: 25px; }
    .h-time b { display: block; font-size: 18px; font-weight: 900; color: #fff; }
    .h-time span { font-size: 11px; opacity: 0.4; text-transform: uppercase; font-weight: 700; }

    .h-badge { 
        min-width: 140px; padding: 10px; border-radius: 12px; 
        font-size: 11px; font-weight: 800; text-transform: uppercase; 
        display: flex; align-items: center; gap: 8px; justify-content: center;
    }

    .h-info { flex: 1; }
    .h-info .target { font-size: 16px; font-weight: 700; margin-bottom: 4px; display: block; }
    .h-info .details { font-size: 13px; color: #7CFF6B; opacity: 0.8; font-family: 'JetBrains Mono', monospace; background: rgba(124, 255, 107, 0.05); padding: 2px 6px; border-radius: 4px; }

    .h-staff { text-align: right; min-width: 180px; }
    .h-staff span { display: block; font-size: 11px; opacity: 0.4; text-transform: uppercase; margin-bottom: 4px; }
    .h-staff b { font-size: 14px; color: #b866ff; background: rgba(184, 102, 255, 0.1); padding: 4px 10px; border-radius: 8px; }
</style>

<div class="h-page">
    <div class="h-top">
        <div>
            <h1>📜 Журнал действий</h1>
            <p>Хронология изменений в базе лояльности</p>
        </div>
        <a href="?page=clients" class="btn-back">← Назад к клиентам</a>
    </div>

    <div class="h-list">
        <?php if (empty($logs)): ?>
            <div style="padding: 100px; text-align: center; background: rgba(255,255,255,0.02); border-radius: 30px; border: 1px dashed rgba(255,255,255,0.1);">
                <span style="font-size: 60px; display: block; margin-bottom: 20px;">📂</span>
                <h3 style="opacity: 0.5;">История пуста</h3>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $log): 
                $style = getActionStyle($log['action_type']);
                $fullName = trim($log['first_name'] . ' ' . $log['last_name']);
                if (!$fullName) $fullName = 'Система';
            ?>
                <div class="h-item">
                    <div class="h-time">
                        <b><?= date('H:i', strtotime($log['created_at'])) ?></b>
                        <span><?= date('d.m.y', strtotime($log['created_at'])) ?></span>
                    </div>

                    <div class="h-badge" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; border: 1px solid <?= str_replace('0.1', '0.2', $style['bg']) ?>;">
                        <span><?= $style['icon'] ?></span>
                        <?= $style['text'] ?>
                    </div>

                    <div class="h-info">
                        <span class="target">
                            <?php if ($log['client_name']): ?>
                                <?= htmlspecialchars($log['client_name']) ?> 
                                <span style="opacity: 0.3; font-weight: 400; font-size: 13px; font-family: monospace;">[<?= htmlspecialchars($log['client_phone']) ?>]</span>
                            <?php else: ?>
                                <span style="color: #ff4444; opacity: 0.6;">[Объект удален]</span>
                            <?php endif; ?>
                        </span>
                        <span class="details"><?= htmlspecialchars($log['new_data']) ?></span>
                    </div>

                    <div class="h-staff">
                        <span>Исполнитель</span>
                        <b>👤 <?= htmlspecialchars($fullName) ?></b>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>