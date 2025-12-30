<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();
$today = date('Y-m-d');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Отмечаем как прочитанное
$pdo->prepare("
    UPDATE shift_transfers
    SET seen_at = NOW()
    WHERE to_user_id = ?
      AND shift_date = ?
      AND status = 'pending'
      AND seen_at IS NULL
")->execute([$user['id'], $today]);

$stmt = $pdo->prepare("
    SELECT st.id, st.created_at, b.name AS branch_name,
           u.first_name, u.last_name
    FROM shift_transfers st
    JOIN users u ON u.id = st.from_user_id
    JOIN branches b ON b.id = st.branch_id
    WHERE st.to_user_id = ?
      AND st.shift_date = ?
      AND st.status = 'pending'
    ORDER BY st.created_at DESC
");
$stmt->execute([$user['id'], $today]);
$items = $stmt->fetchAll();
?>

<style>
    .transfer-container { font-family: 'Inter', sans-serif; max-width: 600px; margin: 0 auto; color: #fff; }
    
    .transfer-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 24px;
        padding: 24px;
        border: 1px solid rgba(120, 90, 255, 0.2); /* Легкий фиолетовый контур */
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
        animation: pulse-border 2s infinite;
    }

    @keyframes pulse-border {
        0% { border-color: rgba(120, 90, 255, 0.2); }
        50% { border-color: rgba(120, 90, 255, 0.5); }
        100% { border-color: rgba(120, 90, 255, 0.2); }
    }

    .transfer-user { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    
    .avatar-circle {
        width: 48px; height: 48px;
        background: linear-gradient(135deg, #785aff, #b866ff);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; font-weight: 700; color: #fff;
    }

    .user-info b { font-size: 18px; display: block; color: #fff; }
    .user-info span { font-size: 13px; color: rgba(255,255,255,0.4); }

    .branch-details {
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex; justify-content: space-between; align-items: center;
    }

    .btn-accept {
        width: 100%;
        padding: 16px;
        background: #00c851;
        color: #fff;
        border: none;
        border-radius: 16px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 200, 81, 0.3);
        transition: 0.3s;
    }

    .btn-accept:hover { transform: translateY(-2px); filter: brightness(1.1); }
    
    .time-stamp {
        display: block; text-align: center; margin-top: 15px;
        font-size: 11px; color: rgba(255,255,255,0.2); text-transform: uppercase; letter-spacing: 1px;
    }
</style>

<div class="transfer-container">
    <div style="margin-bottom: 25px;">
        <h2 style="margin:0; font-weight:600; font-size: 24px;">📥 Запросы на смену</h2>
        <p class="muted" style="font-size: 14px; margin-top: 5px;">Подтвердите получение ключей и ответственности</p>
    </div>

    <?php if (!$items): ?>
        <div class="card" style="text-align: center; padding: 50px; opacity: 0.5;">
            <div style="font-size: 40px; margin-bottom: 15px;">📩</div>
            <div>Новых заявок пока нет</div>
        </div>
    <?php else: ?>
        <?php foreach ($items as $it): 
            $initials = mb_substr($it['last_name'], 0, 1) . mb_substr($it['first_name'], 0, 1);
        ?>
            <div class="transfer-card">
                <div class="transfer-user">
                    <div class="avatar-circle"><?= h($initials) ?></div>
                    <div class="user-info">
                        <b><?= h($it['last_name'].' '.$it['first_name']) ?></b>
                        <span>хочет передать вам смену</span>
                    </div>
                </div>

                <div class="branch-details">
                    <span style="color: rgba(255,255,255,0.4);">Филиал:</span>
                    <b style="color: #785aff;"><?= h($it['branch_name']) ?></b>
                </div>

                <form method="post" action="/cabinet/actions/transfer_accept.php">
                    <input type="hidden" name="transfer_id" value="<?= (int)$it['id'] ?>">
                    <button class="btn-accept">✅ Принять смену</button>
                </form>

                <span class="time-stamp">Заявка от <?= date('H:i', strtotime($it['created_at'])) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
