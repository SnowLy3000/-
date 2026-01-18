<?php
require_once __DIR__ . '/../../includes/db.php';
$user = current_user();

// 1. Подтягиваем основные данные пользователя
$stmt = $pdo->prepare("SELECT status, theme, phone, telegram, gender, created_at, avatar FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$u = $stmt->fetch();

$uid = $user['id'];

// --- ЛОГИКА XP (СУММА ИЗ ЛОГОВ + ЗВАНИЯ) ---
try {
    $xp_query = $pdo->prepare("SELECT SUM(amount) FROM user_xp_log WHERE user_id = ?");
    $xp_query->execute([$uid]);
    $xp_total = (int)$xp_query->fetchColumn();
} catch (Exception $e) { $xp_total = 0; }

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_progress WHERE user_id = ?");
    $stmt->execute([$uid]);
    $tests_done = $stmt->fetchColumn();
} catch (Exception $e) { $tests_done = 0; }

try {
    $stmt = $pdo->prepare("SELECT * FROM user_grades WHERE min_xp <= ? ORDER BY min_xp DESC LIMIT 1");
    $stmt->execute([$xp_total]);
    $current_grade = $stmt->fetch() ?: ['title' => 'Стажер', 'icon' => '🐣', 'min_xp' => 0];
} catch (Exception $e) { $current_grade = ['title' => 'Стажер', 'icon' => '🐣', 'min_xp' => 0]; }

try {
    $next_grade_query = $pdo->prepare("SELECT min_xp FROM user_grades WHERE min_xp > ? ORDER BY min_xp ASC LIMIT 1");
    $next_grade_query->execute([$xp_total]);
    $next_xp_threshold = $next_grade_query->fetchColumn();
} catch (Exception $e) { $next_xp_threshold = false; }

if ($next_xp_threshold) {
    $prev_xp_threshold = (int)$current_grade['min_xp'];
    $xp_percent = (($xp_total - $prev_xp_threshold) / ($next_xp_threshold - $prev_xp_threshold)) * 100;
    $display_lvl = floor($xp_total / 500) + 1; 
} else {
    $xp_percent = 100;
    $display_lvl = "MAX";
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$avatarPath = get_user_avatar($u);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

<style>
    :root { 
        --accent: #785aff; 
        --accent-glow: rgba(120, 90, 255, 0.4); 
        --bg-card: rgba(255, 255, 255, 0.03); 
        --border: rgba(255, 255, 255, 0.08);
    }
    
    .profile-container { font-family: 'Inter', sans-serif; max-width: 900px; margin: 0 auto; color: #fff; padding-bottom: 50px; }
    
    #cropModal, #xpHistoryModal { 
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0, 0, 0, 0.9); z-index: 9999; align-items: center; justify-content: center; 
        flex-direction: column; backdrop-filter: blur(10px); 
    }

    .hero-card {
        background: radial-gradient(circle at top left, rgba(120, 90, 255, 0.15), transparent), var(--bg-card);
        border: 1px solid var(--border); border-radius: 32px; padding: 40px; display: flex; align-items: center; gap: 40px; margin-bottom: 30px; backdrop-filter: blur(10px);
    }

    .avatar-wrapper { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
    .profile-img { width: 100%; height: 100%; border-radius: 40px; object-fit: cover; border: 2px solid var(--accent); box-shadow: 0 15px 35px var(--accent-glow); }
    
    .upload-btn { position: absolute; bottom: -10px; right: -10px; background: var(--accent); width: 44px; height: 44px; border-radius: 15px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 4px solid #0b0f1a; transition: 0.3s; }
    .upload-btn:hover { transform: scale(1.1); }

    .hero-info { flex: 1; }
    .profile-name { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
    
    .grade-badge {
        display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: rgba(120, 90, 255, 0.15); color: #b866ff; border-radius: 12px; font-size: 12px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; border: 1px solid rgba(120, 90, 255, 0.2);
    }

    .badge-status {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: rgba(0, 200, 81, 0.15); color: #00c851; border-radius: 10px; font-size: 10px; font-weight: 800; text-transform: uppercase; margin-left: 10px;
    }

    .xp-section { margin-top: 20px; }
    .xp-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; font-weight: 800; }
    .xp-track { height: 12px; background: rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; }
    .xp-fill { height: 100%; background: linear-gradient(90deg, #785aff, #b866ff); box-shadow: 0 0 20px var(--accent-glow); border-radius: 20px; transition: 1s cubic-bezier(0.17, 0.67, 0.83, 0.67); }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
    .card-dark { background: var(--bg-card); border: 1px solid var(--border); border-radius: 24px; padding: 25px; }
    .data-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .data-row:last-child { border: none; }
    .label { color: rgba(255,255,255,0.4); font-size: 13px; }
    .val { font-weight: 600; font-size: 14px; }

    .achievements-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
    .medal-box { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 20px 10px; text-align: center; border: 1px solid transparent; }
    .medal-box.unlocked { background: rgba(120, 90, 255, 0.05); border-color: rgba(120, 90, 255, 0.2); }
    .medal-icon { font-size: 32px; margin-bottom: 10px; filter: grayscale(1); opacity: 0.2; }
    .unlocked .medal-icon { filter: grayscale(0); opacity: 1; text-shadow: 0 0 15px var(--accent-glow); }
    .medal-name { font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.3; }
    .unlocked .medal-name { opacity: 1; color: var(--accent); }

    .btn-history { background: none; border: none; color: var(--accent); font-size: 11px; font-weight: 700; cursor: pointer; margin-top: 12px; padding: 0; display: flex; align-items: center; gap: 5px; opacity: 0.7; transition: 0.3s; }
    .btn-history:hover { opacity: 1; }
</style>

<div class="profile-container">
    <div class="hero-card">
        <div class="avatar-wrapper">
            <img src="<?= $avatarPath ?>" class="profile-img" id="avatar-preview">
            <label for="avatar-input" class="upload-btn"><span>📷</span></label>
            <input type="file" id="avatar-input" style="display: none;" accept="image/*">
        </div>

        <div class="hero-info">
            <div style="display: flex; align-items: center;">
                <div class="grade-badge">
                    <span><?= $current_grade['icon'] ?></span>
                    <?= h($current_grade['title']) ?>
                </div>
                <div class="badge-status">
                    <span style="width: 6px; height: 6px; background: #00c851; border-radius: 50%;"></span>
                    <?= h($u['status']) ?>
                </div>
            </div>
            <div class="profile-name"><?= h($user['first_name'].' '.$user['last_name']) ?></div>
            
            <div class="xp-section">
                <div class="xp-header">
                    <span style="color: var(--accent);">LVL <?= $display_lvl ?></span>
                    <span style="opacity: 0.5;"><?= $xp_total ?> / <?= ($next_xp_threshold ?: 'MAX') ?> XP</span>
                </div>
                <div class="xp-track">
                    <div class="xp-fill" style="width: <?= $xp_percent ?>%"></div>
                </div>
                <button onclick="openXPHistory()" class="btn-history">📜 ПОСМОТРЕТЬ ИСТОРИЮ ОПЫТА</button>
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="card-dark">
            <div class="data-row"><span class="label">📱 Телефон</span><span class="val"><?= h($u['phone'] ?? '-') ?></span></div>
            <div class="data-row"><span class="label">💬 Telegram</span><span class="val"><?= h($u['telegram'] ?? '-') ?></span></div>
            <div class="data-row"><span class="label">👤 Пол</span><span class="val"><?= h($u['gender'] ?? '-') ?></span></div>
        </div>
        <div class="card-dark">
            <div class="data-row"><span class="label">🎨 Тема</span><span class="val"><?= h($u['theme'] ?? 'Dark Future') ?></span></div>
            <div class="data-row"><span class="label">📅 Регистрация</span><span class="val"><?= date('d.m.Y', strtotime($u['created_at'])) ?></span></div>
            <div class="data-row"><span class="label">🔑 Системный ID</span><span class="val" style="color:var(--accent);">#<?= $user['id'] ?></span></div>
        </div>
    </div>

    <div class="card-dark">
        <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 800;">🏆 КОЛЛЕКЦИЯ ДОСТИЖЕНИЙ</h3>
        <div class="achievements-grid">
            <div class="medal-box <?= ($xp_total > 0) ? 'unlocked' : '' ?>"><div class="medal-icon">🎯</div><div class="medal-name">Новичок</div></div>
            <div class="medal-box <?= ($tests_done >= 5) ? 'unlocked' : '' ?>"><div class="medal-icon">🔥</div><div class="medal-name">Знаток</div></div>
            <div class="medal-box <?= ($tests_done >= 10) ? 'unlocked' : '' ?>"><div class="medal-icon">👑</div><div class="medal-name">Эксперт</div></div>
            <div class="medal-box <?= ($tests_done > 0) ? 'unlocked' : '' ?>"><div class="medal-icon">📖</div><div class="medal-name">Книжник</div></div>
            <div class="medal-box <?= ($display_lvl != "MAX" && (int)$display_lvl >= 5) ? 'unlocked' : '' ?>"><div class="medal-icon">💎</div><div class="medal-name">Легенда</div></div>
        </div>
    </div>
</div>

<div id="cropModal">
    <div id="crop-area" style="width: 300px; height: 300px;"></div>
    <div style="margin-top: 20px;">
        <button onclick="saveCrop()" style="background: var(--accent); color: #fff; border: none; padding: 12px 25px; border-radius: 12px; font-weight: bold; cursor: pointer;">СОХРАНИТЬ</button>
        <button onclick="closeCrop()" style="background: rgba(255,255,255,0.1); color: #fff; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; margin-left: 10px;">ОТМЕНА</button>
    </div>
</div>

<div id="xpHistoryModal">
    <div style="background: #161621; width: 100%; max-width: 450px; border-radius: 24px; border: 1px solid rgba(120, 90, 255, 0.3); overflow: hidden;">
        <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #785aff;">📜 ИСТОРИЯ ОПЫТА</h3>
            <button onclick="closeXPHistory()" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; opacity: 0.5;">&times;</button>
        </div>
        <div id="xp-history-content" style="max-height: 400px; overflow-y: auto; padding: 15px;">
            <div style="text-align: center; padding: 20px; opacity: 0.5;">Загрузка...</div>
        </div>
    </div>
</div>

<script>
let cropper = null;
const avatarInput = document.getElementById('avatar-input');

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

// --- КРОП АВАТАРА ---
avatarInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cropModal').style.display = 'flex';
            if (cropper) cropper.destroy();
            cropper = new Croppie(document.getElementById('crop-area'), {
                viewport: { width: 200, height: 200, type: 'square' },
                boundary: { width: 300, height: 300 },
                showZoomer: true
            });
            cropper.bind({ url: e.target.result });
        }
        reader.readAsDataURL(this.files[0]);
    }
});

function saveCrop() {
    cropper.result({ type: 'base64', size: { width: 512, height: 512 }, format: 'jpeg' }).then(function(base64) {
        fetch('actions/upload_avatar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'image=' + encodeURIComponent(base64)
        }).then(() => location.reload());
    });
}
function closeCrop() { document.getElementById('cropModal').style.display = 'none'; avatarInput.value = ''; }

// --- ИСТОРИЯ XP ---
async function openXPHistory() {
    const modal = document.getElementById('xpHistoryModal');
    const content = document.getElementById('xp-history-content');
    modal.style.display = 'flex';
    
    try {
        const res = await fetch(`api/get_xp_history.php`);
        const data = await res.json();
        if (data.length === 0) {
            content.innerHTML = '<div style="text-align:center; padding:20px; opacity:0.5;">История пуста</div>';
            return;
        }
        content.innerHTML = data.map(i => `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; background:rgba(255,255,255,0.02); border-radius:12px; margin-bottom:8px; border:1px solid rgba(255,255,255,0.03);">
                <div>
                    <div style="font-size:13px; font-weight:600;">${escapeHtml(i.reason)}</div>
                    <div style="font-size:10px; opacity:0.4;">${i.date}</div>
                </div>
                <div style="color:#00c851; font-weight:800;">+${i.amount} XP</div>
            </div>
        `).join('');
    } catch (e) { content.innerHTML = '<div style="text-align:center; color:#ff4444;">Ошибка загрузки</div>'; }
}

function closeXPHistory() { document.getElementById('xpHistoryModal').style.display = 'none'; }

window.onclick = function(e) {
    if (e.target.id == 'xpHistoryModal') closeXPHistory();
    if (e.target.id == 'cropModal') closeCrop();
}
</script>