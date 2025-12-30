<?php
require_once __DIR__ . '/../../includes/db.php';
$user = current_user();

$stmt = $pdo->prepare("SELECT status, theme, phone, telegram, gender, created_at FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$u = $stmt->fetch();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$initials = mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1);
?>

<style>
    .profile-container { font-family: 'Inter', sans-serif; max-width: 800px; margin: 0 auto; color: #fff; }

    /* Верхняя карточка с аватаром */
    .profile-main-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 24px;
        padding: 30px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 20px;
    }

    .profile-avatar {
        width: 80px; height: 80px;
        background: linear-gradient(135deg, #785aff, #b866ff);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; font-weight: 700; color: #fff;
        margin: 0 auto 15px;
        box-shadow: 0 10px 20px rgba(120, 90, 255, 0.2);
    }

    .profile-name { font-size: 22px; font-weight: 600; margin-bottom: 5px; }
    .profile-status {
        display: inline-block;
        padding: 4px 12px;
        background: rgba(0, 200, 81, 0.1);
        color: #00c851;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Сетка данных */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .info-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 15px;
        border-radius: 16px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .info-row:last-child { border: none; }
    .info-label { color: rgba(255,255,255,0.3); }
    .info-value { color: #fff; font-weight: 500; }

    /* Секция статистики (будущая) */
    .stats-preview {
        background: rgba(120, 90, 255, 0.03);
        border: 1px dashed rgba(120, 90, 255, 0.2);
        padding: 20px;
        border-radius: 20px;
    }

    .stats-title {
        font-size: 16px; font-weight: 600; color: #785aff;
        margin-bottom: 15px; display: flex; align-items: center; gap: 8px;
    }

    .stats-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
    }

    .stat-placeholder {
        background: rgba(255,255,255,0.03);
        padding: 10px 15px;
        border-radius: 10px;
        font-size: 12px;
        color: rgba(255,255,255,0.4);
        display: flex; align-items: center; gap: 8px;
    }
</style>

<div class="profile-container">
    
    <div class="profile-main-card">
        <div class="profile-avatar"><?= h($initials) ?></div>
        <div class="profile-name"><?= h($user['first_name'].' '.$user['last_name']) ?></div>
        <div class="profile-status"><?= h($u['status']) ?></div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="info-row">
                <span class="info-label">📱 Телефон</span>
                <span class="info-value"><?= h($u['phone'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">💬 Telegram</span>
                <span class="info-value"><?= h($u['telegram'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">👤 Пол</span>
                <span class="info-value"><?= h($u['gender'] ?? '-') ?></span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-row">
                <span class="info-label">🎨 Тема</span>
                <span class="info-value"><?= h($u['theme'] ?? 'Темная') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">📅 Регистрация</span>
                <span class="info-value"><?= date('d.m.Y', strtotime($u['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">🔑 ID</span>
                <span class="info-value">#<?= $user['id'] ?></span>
            </div>
        </div>
    </div>

    <div class="stats-preview">
        <div class="stats-title">📊 Личная эффективность (в разработке)</div>
        <div class="stats-list">
            <div class="stat-placeholder">📈 Продажи и чеки</div>
            <div class="stat-placeholder">🔗 Кросс-продажи</div>
            <div class="stat-placeholder">🎁 Участие в акциях</div>
            <div class="stat-placeholder">⏰ Дисциплина</div>
            <div class="stat-placeholder">🗓 График смен</div>
            <div class="stat-placeholder">🎓 Обучение и тесты</div>
        </div>
    </div>

</div>
