<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_users');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Определяем ID текущего пользователя из сессии
$current_session_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0);

/* ================= 1. ЛОГИКА ДЕЙСТВИЙ ================= */

if (isset($_GET['archive_id'])) {
    $archive_id = (int)$_GET['archive_id'];
    
    $check = $pdo->prepare("SELECT r.name FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.id = ?");
    $check->execute([$archive_id]);
    $roleName = $check->fetchColumn();

    if ($roleName !== 'Owner' && $archive_id !== (int)$current_session_id) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$archive_id]);
        echo "<script>window.location.href='index.php?page=users&success=1';</script>";
        exit;
    }
}

/* ================= 2. ПОЛУЧЕНИЕ ДАННЫХ ================= */

$stmt = $pdo->query("
    SELECT u.*, 
           r.name as role_display,
           p.name as position_display
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN user_positions up ON u.id = up.user_id
    LEFT JOIN positions p ON up.position_id = p.id
    ORDER BY CASE WHEN u.status = 'active' THEN 1 ELSE 2 END, u.last_name ASC
");
$users = $stmt->fetchAll();
?>

<style>
    .users-layout { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    .users-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .btn-add-user { background: #785aff; color: #fff; padding: 12px 25px; border-radius: 14px; text-decoration: none; font-weight: 800; font-size: 13px; transition: 0.3s; }
    
    .users-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 24px; overflow: hidden; }
    .u-table { width: 100%; border-collapse: collapse; }
    .u-table th { padding: 15px 20px; text-align: left; font-size: 10px; text-transform: uppercase; color: #444; letter-spacing: 1px; background: rgba(255,255,255,0.01); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .u-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; }
    .u-table tr:hover { background: rgba(255, 255, 255, 0.02); }

    .u-info { display: flex; align-items: center; gap: 12px; }
    .u-avatar { width: 38px; height: 38px; border-radius: 10px; background: #16161a; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #785aff; border: 1px solid #222; font-size: 14px; }
    
    .u-badge { padding: 4px 10px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    .u-role-owner { background: rgba(241, 196, 15, 0.1); color: #f1c40f; border-color: rgba(241, 196, 15, 0.2); }
    .u-role-admin { background: rgba(120, 90, 255, 0.1); color: #785aff; border-color: rgba(120, 90, 255, 0.2); }
    .u-role-staff { background: rgba(255, 255, 255, 0.05); color: #888; border-color: rgba(255, 255, 255, 0.1); }

    .u-status { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; }
    .u-status-active { background: rgba(0, 200, 81, 0.1); color: #00c851; }
    .u-status-inactive { background: rgba(255, 68, 68, 0.1); color: #ff4444; }

    .u-action { text-decoration: none; font-size: 16px; margin-left: 12px; opacity: 0.4; transition: 0.2s; color: #fff; cursor: pointer; }
    .u-action:hover { opacity: 1; transform: scale(1.1); }
</style>

<div class="users-layout">
    <div class="users-header">
        <div>
            <h1 style="margin:0; font-size: 24px; font-weight: 900;">👥 Команда</h1>
            <p style="margin:5px 0 0 0; opacity: 0.4; font-size: 13px;">Управление доступом и ролями персонала</p>
        </div>
        <a href="?page=user_add" class="btn-add-user">+ НОВЫЙ СОТРУДНИК</a>
    </div>

    <div class="users-card">
        <table class="u-table">
            <thead>
                <tr>
                    <th>Сотрудник</th>
                    <th>Уровень доступа</th>
                    <th>Должность</th>
                    <th>Был в сети</th>
                    <th>Статус</th>
                    <th style="text-align: right;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $roleStyle = 'u-role-staff';
                    if ($u['role_display'] === 'Owner') $roleStyle = 'u-role-owner';
                    if ($u['role_display'] === 'Admin') $roleStyle = 'u-role-admin';
                ?>
                <tr>
                    <td>
                        <div class="u-info">
                            <div class="u-avatar"><?= mb_substr($u['last_name'], 0, 1) ?></div>
                            <div>
                                <div style="font-weight: 700; font-size: 14px;"><?= h($u['last_name'].' '.$u['first_name']) ?></div>
                                <div style="font-size: 11px; opacity: 0.4;">
                                    <?= h($u['email'] ?? ($u['login'] ?? ($u['phone'] ?? 'ID: '.$u['id']))) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><span class="u-badge <?= $roleStyle ?>"><?= h($u['role_display'] ?: 'БЕЗ РОЛИ') ?></span></td>
                    <td><span style="font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);"><?= h($u['position_display'] ?: '—') ?></span></td>
                    <td>
                        <?php if ($u['last_seen']): ?>
                            <div style="font-size: 12px; font-weight: 600;"><?= date('d.m.y', strtotime($u['last_seen'])) ?></div>
                            <div style="font-size: 10px; opacity: 0.3;"><?= date('H:i', strtotime($u['last_seen'])) ?></div>
                        <?php else: ?>
                            <span style="opacity: 0.15; font-size: 11px;">Никогда</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="u-status <?= $u['status'] === 'active' ? 'u-status-active' : 'u-status-inactive' ?>">
                            <?= $u['status'] === 'active' ? '● Активен' : '○ Отключен' ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <a onclick="openBonusModal(<?= $u['id'] ?>, '<?= h($u['last_name'].' '.$u['first_name']) ?>')" class="u-action" title="Начислить бонус XP">🏆</a>
                        
                        <a href="?page=user_edit&id=<?= $u['id'] ?>" class="u-action" title="Редактировать">✏️</a>
                        
                        <?php if($u['role_display'] !== 'Owner' && (int)$u['id'] !== (int)$current_session_id): ?>
                            <a href="javascript:void(0)" 
                               onclick="if(confirm('Заблокировать сотрудника?')) window.location.href='index.php?page=users&archive_id=<?= $u['id'] ?>'" 
                               class="u-action" style="color: #ff4444;" title="Заблокировать">✕</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="bonusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 99999; backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div style="background: #16161a; border: 1px solid #333; padding: 35px; border-radius: 28px; width: 100%; max-width: 400px;">
        <h2 style="margin-top: 0; font-size: 20px; margin-bottom: 20px;">Начислить бонус: <span id="bonusUserName" style="color:#785aff;"></span></h2>
        <form action="/admin/actions/save_gamification.php" method="POST">
            <input type="hidden" name="action" value="give_manual_xp">
            <input type="hidden" name="user_id" id="bonusUserId" value="">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 11px; opacity: 0.4; margin-bottom: 8px; text-transform: uppercase;">Количество XP</label>
                <input type="number" name="amount" required placeholder="Напр: 100" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 14px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-size: 11px; opacity: 0.4; margin-bottom: 8px; text-transform: uppercase;">Причина</label>
                <input type="text" name="reason" required placeholder="Напр: Помощь в инвентаризации" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 14px; border-radius: 14px; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="document.getElementById('bonusModal').style.display='none'" style="flex: 1; background: rgba(255,255,255,0.05); color: #fff; border: none; padding: 15px; border-radius: 14px; cursor: pointer; font-weight: 600;">Отмена</button>
                <button type="submit" style="flex: 2; background: #785aff; color: #fff; border: none; padding: 15px; border-radius: 14px; font-weight: 700; cursor: pointer;">Вручить XP</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBonusModal(id, name) {
    document.getElementById('bonusUserId').value = id;
    document.getElementById('bonusUserName').innerText = name;
    document.getElementById('bonusModal').style.display = 'flex';
}

// Закрытие модалки по клику на фон
window.onclick = function(event) {
    if (event.target == document.getElementById('bonusModal')) {
        document.getElementById('bonusModal').style.display = "none";
    }
}
</script>