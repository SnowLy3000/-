<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';
require_auth();
$user = current_user();
$isAdmin = has_role('Admin') || has_role('Owner');

// Начальный список каналов
$channels = $pdo->query("SELECT * FROM chat_channels WHERE status = 'active' AND slug LIKE 'stock_%' ORDER BY id DESC LIMIT 15")->fetchAll();
?>

<div class="chat-layout" data-user-id="<?= $user['id'] ?>">
    <audio id="chat-sound" src="https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3" preload="auto"></audio>

    <div class="chat-sidebar">
        <div class="sidebar-brand">Messenger</div>
        
        <div class="nav-menu">
            <div class="nav-item active" id="nav-general" onclick="switchTab('general')">💬 Общий чат</div>
            <div class="nav-item" id="nav-notepad" onclick="switchTab('notepad_<?= $user['id'] ?>', '📓 Блокнот')">📓 Мой блокнот</div>
            <div class="nav-item" id="nav-contacts" onclick="switchTab('contacts_list', '👥 Команда')">👥 Команда</div>
            
            <div class="menu-sep">Личные сообщения</div>
            <div id="private-chats-list"></div>

            <div class="menu-sep">Запросы товара</div>
            <div id="stock-channels-list">
                <?php foreach($channels as $c): ?>
                    <div class="nav-item" id="nav-<?= $c['slug'] ?>" onclick="switchTab('<?= $c['slug'] ?>', '<?= htmlspecialchars($c['name']) ?>')"><?= htmlspecialchars($c['name']) ?></div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: auto; padding: 15px;">
                <button class="btn-request" onclick="showModal('modalStock')">🔍 Найти товар</button>
            </div>
        </div>
    </div>

    <div class="chat-main">
        <div id="stock-alerts-top" style="display:none;"></div>
        
        <div class="chat-head">
            <span id="chat-title"># Общий чат</span>
            <div id="chat-actions"></div>
        </div>

        <div id="participants-area" class="participants-bar" style="display:none;"></div>

        <div id="messages-box" class="msg-container"></div>
        
        <div id="contacts-box" class="msg-container" style="display:none;">
            <div class="contacts-list" id="contacts-inner"></div>
        </div>

        <div class="chat-footer">
            <form id="chat-form" class="input-wrapper">
                <input type="text" id="chat-input" placeholder="Введите сообщение..." autocomplete="off">
                <button type="submit">➤</button>
            </form>
        </div>
    </div>
</div>

<div id="modalStock" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h4 style="margin:0 0 15px 0; color: #fff; font-size: 18px;">Новый запрос товара</h4>
        <input type="text" id="p_search" class="modal-input" placeholder="Начните вводить название...">
        <div id="p_results" class="search-results"></div>
        <button class="btn-close-modal" onclick="hideModal('modalStock')" style="margin-top: 20px; width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: none; color: #aaa; border-radius: 12px; cursor: pointer; font-weight: 600;">Отмена</button>
    </div>
</div>

<link rel="stylesheet" href="/assets/css/chat.css?v=<?=time()?>">
<script src="/assets/js/chat.js?v=<?=time()?>"></script>