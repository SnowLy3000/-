<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user  = current_user();
$today = date('Y-m-d');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Данные о смене, сессии и передаче остаются без изменений (логика из вашего кода) **/
$stmt = $pdo->prepare("SELECT ws.branch_id, ws.shift_date, b.name AS branch_name, b.shift_start_time, b.shift_end_time FROM work_shifts ws JOIN branches b ON b.id = ws.branch_id WHERE ws.user_id = ? AND ws.shift_date = ? LIMIT 1");
$stmt->execute([$user['id'], $today]);
$shift = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM shift_sessions WHERE user_id = ? AND shift_date = ? ORDER BY checkin_at DESC LIMIT 1");
$stmt->execute([$user['id'], $today]);
$session = $stmt->fetch();

$transferOut = null; $transferIn = null;
$stmt = $pdo->prepare("SELECT st.status, st.created_at, st.confirmed_at, u.first_name, u.last_name FROM shift_transfers st JOIN users u ON u.id = st.to_user_id WHERE st.from_user_id = ? AND st.shift_date = ? ORDER BY st.id DESC LIMIT 1");
$stmt->execute([$user['id'], $today]);
$transferOut = $stmt->fetch();

$stmt = $pdo->prepare("SELECT st.status, st.created_at, st.confirmed_at, u.first_name, u.last_name FROM shift_transfers st JOIN users u ON u.id = st.from_user_id WHERE st.to_user_id = ? AND st.shift_date = ? AND st.status IN ('pending','accepted') ORDER BY st.id DESC LIMIT 1");
$stmt->execute([$user['id'], $today]);
$transferIn = $stmt->fetch();
?>

<style>
    .checkin-container { font-family: 'Inter', sans-serif; max-width: 600px; margin: 0 auto; color: #fff; }
    
    /* Карточка статуса */
    .status-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 24px;
        padding: 25px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
        margin-bottom: 20px;
    }

    .branch-info h2 { font-size: 22px; margin: 0 0 5px 0; font-weight: 600; }
    .work-time { font-size: 13px; color: rgba(255,255,255,0.4); letter-spacing: 0.5px; }

    /* Бейджи состояний */
    .state-indicator {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 16px; border-radius: 12px; font-size: 14px; font-weight: 700;
        margin: 20px 0; text-transform: uppercase;
    }
    .state-active { background: rgba(0, 200, 81, 0.1); color: #00c851; }
    .state-pending { background: rgba(255, 152, 0, 0.1); color: #ff9800; }
    .state-waiting { background: rgba(255, 255, 255, 0.05); color: #aaa; }

    /* Кнопки */
    .action-btn {
        width: 100%; padding: 16px; border-radius: 16px; border: none;
        font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s;
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-start { background: #785aff; color: #fff; box-shadow: 0 8px 20px rgba(120, 90, 255, 0.3); }
    .btn-end { background: rgba(255, 255, 255, 0.05); color: #ff4444; border: 1px solid rgba(255, 68, 68, 0.2); }
    .btn-start:hover { transform: translateY(-2px); filter: brightness(1.1); }

    /* Форма передачи */
    .transfer-box {
        background: rgba(255, 255, 255, 0.02);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        border-radius: 20px; padding: 20px; margin-top: 20px;
    }
    .transfer-box label { font-size: 12px; color: rgba(255,255,255,0.3); text-transform: uppercase; display: block; margin-bottom: 10px; }
    
    select {
        width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        padding: 12px; border-radius: 12px; color: #fff; outline: none; margin-bottom: 15px;
    }

    .time-badge { font-family: monospace; font-size: 12px; opacity: 0.5; }
</style>

<div class="checkin-container">
    <div class="card" style="margin-bottom:15px; background:none; border:none;">
        <h1 style="font-size: 24px; margin:0;">Check-in</h1>
    </div>

    <?php if (!$shift && !$session && !$transferIn): ?>
        <div class="status-card" style="opacity: 0.6;">
            <div style="font-size: 40px; margin-bottom: 10px;">☕</div>
            <p>Сегодня у вас выходной по графику</p>
        </div>
    <?php else: ?>

        <div class="status-card">
            <div class="branch-info">
                <h2><?= h($shift['branch_name'] ?? 'Удаленный филиал') ?></h2>
                <?php if (!empty($shift['shift_start_time'])): ?>
                    <div class="work-time">
                        ⏰ Режим работы: <?= substr($shift['shift_start_time'], 0, 5) ?> 
                        <?= !empty($shift['shift_end_time']) ? '— '.substr($shift['shift_end_time'], 0, 5) : '' ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($transferIn && $transferIn['status'] === 'pending'): ?>
                <div class="state-indicator state-pending">🟠 Ожидает принятия</div>
                <div style="font-size: 13px; color: #bbb;">
                    Передал: <b><?= h($transferIn['last_name'].' '.$transferIn['first_name']) ?></b>
                </div>

            <?php elseif ($transferOut && $transferOut['status'] === 'pending'): ?>
                <div class="state-indicator state-pending">🟠 Передача в процессе</div>
                <div style="font-size: 13px; color: #bbb;">
                    Кому: <b><?= h($transferOut['last_name'].' '.$transferOut['first_name']) ?></b>
                </div>

            <?php elseif ($session && !$session['checkout_at']): ?>
                <div class="state-indicator state-active">🟢 Смена активна</div>
                <div class="time-badge">Начало: <?= date('H:i', strtotime($session['checkin_at'])) ?></div>

            <?php elseif ($session && $session['checkout_at']): ?>
                <div class="state-indicator state-waiting">🏁 Завершена</div>
                <div class="time-badge">
                    <?= date('H:i', strtotime($session['checkin_at'])) ?> — <?= date('H:i', strtotime($session['checkout_at'])) ?>
                </div>

            <?php else: ?>
                <div class="state-indicator state-waiting">⚪ Не начата</div>
            <?php endif; ?>
        </div>

        <?php if ($shift && !$session && !($transferOut && $transferOut['status'] === 'pending')): ?>
            <form method="post" action="/cabinet/pages/checkin_start.php">
                <input type="hidden" name="branch_id" value="<?= (int)$shift['branch_id'] ?>">
                <input type="hidden" name="shift_date" value="<?= h($shift['shift_date']) ?>">
                <button class="action-btn btn-start">🚀 Начать смену</button>
            </form>
        <?php endif; ?>

        <?php if ($session && !$session['checkout_at'] && !($transferOut && $transferOut['status'] === 'pending')): ?>
            <form method="post" action="/cabinet/pages/checkin_end.php" style="margin-bottom: 15px;">
                <button class="action-btn btn-end">⏹ Завершить смену</button>
            </form>

            <div class="transfer-box">
                <label>Передача текущей смены</label>
                <form method="post" action="/cabinet/actions/transfer_request.php">
                    <input type="hidden" name="branch_id" value="<?= (int)$shift['branch_id'] ?>">
                    <input type="hidden" name="shift_date" value="<?= h($today) ?>">

                    <select name="to_user_id" required>
                        <option value="" disabled selected>Выберите коллегу...</option>
                        <?php
                        $users = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE status = 'active' AND id != ? ORDER BY last_name, first_name");
                        $users->execute([$user['id']]);
                        foreach ($users as $u):
                        ?>
                            <option value="<?= (int)$u['id'] ?>">
                                <?= h($u['last_name'].' '.$u['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="action-btn" style="background: rgba(120, 90, 255, 0.1); color: #785aff; border: 1px solid #785aff;">
                        🤝 Передать смену
                    </button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
