<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user  = current_user();
$userId = (int)$user['id'];
$today = date('Y-m-d');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 1. ПРОВЕРКА ГРАФИКА НА СЕГОДНЯ
$stmt = $pdo->prepare("
    SELECT ws.branch_id, b.name AS branch_name, b.shift_start_time, b.shift_end_time 
    FROM work_shifts ws 
    JOIN branches b ON b.id = ws.branch_id 
    WHERE ws.user_id = ? AND ws.shift_date = ? 
    LIMIT 1
");
$stmt->execute([$userId, $today]);
$shift = $stmt->fetch();

// 2. ПРОВЕРКА ТЕКУЩЕЙ СЕССИИ (Открыта ли смена прямо сейчас)
$stmt = $pdo->prepare("
    SELECT * FROM shift_sessions 
    WHERE user_id = ? AND shift_date = ? 
    ORDER BY checkin_at DESC LIMIT 1
");
$stmt->execute([$userId, $today]);
$session = $stmt->fetch();

// 3. ПРОВЕРКА ПЕРЕДАЧ (Входящие и Исходящие)
$stmt = $pdo->prepare("
    SELECT st.*, u.first_name, u.last_name 
    FROM shift_transfers st 
    JOIN users u ON u.id = st.to_user_id 
    WHERE st.from_user_id = ? AND st.shift_date = ? 
    ORDER BY st.id DESC LIMIT 1
");
$stmt->execute([$userId, $today]);
$transferOut = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT st.*, u.first_name, u.last_name 
    FROM shift_transfers st 
    JOIN users u ON u.id = st.from_user_id 
    WHERE st.to_user_id = ? AND st.shift_date = ? AND st.status = 'pending' 
    ORDER BY st.id DESC LIMIT 1
");
$stmt->execute([$userId, $today]);
$transferIn = $stmt->fetch();
?>

<style>
    .checkin-container { font-family: 'Inter', sans-serif; max-width: 600px; margin: 0 auto; color: #fff; }
    .status-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 24px; padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center; margin-bottom: 20px;
        backdrop-filter: blur(10px);
    }
    .branch-name { font-size: 24px; font-weight: 800; color: #785aff; margin-bottom: 5px; }
    .work-time { font-size: 13px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; }
    
    .state-badge {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 20px; border-radius: 14px; font-size: 14px; font-weight: 800;
        margin: 20px 0; text-transform: uppercase;
    }
    .state-active { background: rgba(0, 200, 81, 0.1); color: #00c851; border: 1px solid rgba(0, 200, 81, 0.2); }
    .state-off { background: rgba(255, 255, 255, 0.05); color: rgba(255,255,255,0.3); }
    .state-pending { background: rgba(255, 152, 0, 0.1); color: #ff9800; border: 1px solid rgba(255, 152, 0, 0.2); }

    .action-btn {
        width: 100%; padding: 18px; border-radius: 18px; border: none;
        font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.3s;
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-start { background: #785aff; color: #fff; box-shadow: 0 10px 25px rgba(120, 90, 255, 0.3); }
    .btn-end { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: 1px solid rgba(255, 68, 68, 0.2); }
    .btn-start:hover { transform: translateY(-3px); filter: brightness(1.2); }

    .transfer-section {
        background: rgba(120, 90, 255, 0.05);
        border: 1px dashed rgba(120, 90, 255, 0.2);
        border-radius: 20px; padding: 20px; margin-top: 20px;
    }
    select {
        width: 100%; background: #1a1a24; border: 1px solid rgba(255,255,255,0.1);
        padding: 14px; border-radius: 12px; color: #fff; margin-bottom: 15px;
    }
</style>

<div class="checkin-container">
    <h1 style="margin-bottom: 25px; font-weight: 800;">Check-in</h1>

    <?php if (!$shift && !$session && !$transferIn): ?>
        <div class="status-card">
            <div style="font-size: 50px; margin-bottom: 15px;">🏝️</div>
            <div class="branch-name">Выходной</div>
            <p style="opacity: 0.5;">Сегодня вас нет в графике работы.</p>
        </div>
    <?php else: ?>
        <div class="status-card">
            <div class="branch-info">
                <div class="branch-name"><?= h($shift['branch_name'] ?? 'Переданная смена') ?></div>
                <div class="work-time">
                    📅 <?= date('d.m.Y') ?> 
                    <?php if($shift): ?> 
                        | ⏰ <?= substr($shift['shift_start_time'], 0, 5) ?> — <?= substr($shift['shift_end_time'], 0, 5) ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($session && !$session['checkout_at']): ?>
                <div class="state-badge state-active">🟢 Смена активна</div>
                <div style="opacity: 0.6; font-size: 13px;">Начали в: <?= date('H:i', strtotime($session['checkin_at'])) ?></div>
            <?php elseif ($transferIn): ?>
                <div class="state-badge state-pending">🟠 Входящая передача</div>
                <p>Коллега <b><?= h($transferIn['first_name']) ?></b> передает вам смену.</p>
            <?php elseif ($session && $session['checkout_at']): ?>
                <div class="state-badge state-off">🏁 Смена завершена</div>
            <?php else: ?>
                <div class="state-badge state-off">⚪ Ожидание начала</div>
            <?php endif; ?>
        </div>

        <?php if (!$session): ?>
            <form method="post" action="actions/checkin_start.php">
                <input type="hidden" name="branch_id" value="<?= (int)$shift['branch_id'] ?>">
                <button class="action-btn btn-start">🚀 НАЧАТЬ РАБОЧИЙ ДЕНЬ</button>
            </form>
        <?php elseif ($session && !$session['checkout_at']): ?>
            <form method="post" action="actions/checkin_end.php">
                <input type="hidden" name="shift_id" value="<?= (int)$session['id'] ?>">
                <button class="action-btn btn-end" onclick="return confirm('Завершить смену?')">⏹ ЗАВЕРШИТЬ СМЕНУ</button>
            </form>

            <div class="transfer-section">
                <label style="display:block; margin-bottom:10px; font-size:12px; opacity:0.5;">ПЕРЕДАТЬ СМЕНУ КОЛЛЕГЕ</label>
                <form method="post" action="actions/transfer_request.php">
                    <input type="hidden" name="branch_id" value="<?= (int)$shift['branch_id'] ?>">
                    <select name="to_user_id" required>
                        <option value="" disabled selected>Выберите сотрудника...</option>
                        <?php
                        $users = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE status = 'active' AND id != ?");
                        $users->execute([$userId]);
                        foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= h($u['first_name'].' '.$u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="action-btn" style="background:#1a1a24; color:#785aff; border: 1px solid #785aff;">🤝 ПЕРЕДАТЬ</button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>