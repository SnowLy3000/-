<?php
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
$isAdmin = has_role('Admin') || has_role('Owner');
$userId = $_SESSION['user']['id'];

// --- ОБРАБОТКА ПОСТ-ЗАПРОСОВ ---
$redirect = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_glass']) && $isAdmin) {
        $pdo->prepare("DELETE FROM glass_compatibility WHERE id = ?")->execute([(int)$_POST['glass_id']]);
        $redirect = '?page=glass&success=deleted';
    }
    
    if (isset($_POST['save_glass']) && $isAdmin) {
        $brand = trim($_POST['brand']);
        $main = trim($_POST['main_model']);
        $list = trim($_POST['compatible_list']);
        $id = !empty($_POST['glass_id']) ? (int)$_POST['glass_id'] : null;
        
        if ($id) {
            $pdo->prepare("UPDATE glass_compatibility SET brand=?, main_model=?, compatible_list=?, updated_at=NOW() WHERE id=?")
                ->execute([$brand, $main, $list, $id]);
        } else {
            $pdo->prepare("INSERT INTO glass_compatibility (brand, main_model, compatible_list, added_by) VALUES (?,?,?,?)")
                ->execute([$brand, $main, $list, $userId]);
        }
        $redirect = '?page=glass&success=saved';
    }

    if (isset($_POST['send_suggestion'])) {
        $pdo->prepare("INSERT INTO glass_suggestions (user_id, brand, main_model, compatible_list) VALUES (?,?,?,?)")
            ->execute([$userId, trim($_POST['brand']), trim($_POST['main_model']), trim($_POST['compatible_list'])]);
        $redirect = '?page=glass&success=sent';
    }

    if (isset($_POST['moderate_suggest']) && $isAdmin) {
        $sid = (int)$_POST['suggest_id'];
        $status = $_POST['moderate_suggest'];
        
        if ($status === 'approved') {
            $sug = $pdo->prepare("SELECT * FROM glass_suggestions WHERE id = ?");
            $sug->execute([$sid]);
            $d = $sug->fetch();
            $pdo->prepare("INSERT INTO glass_compatibility (brand, main_model, compatible_list, added_by) VALUES (?,?,?,?)")
                ->execute([$d['brand'], $d['main_model'], $d['compatible_list'], $d['user_id']]);
        }
        $pdo->prepare("UPDATE glass_suggestions SET status = ? WHERE id = ?")->execute([$status, $sid]);
        $redirect = '?page=glass&tab=requests&success=moderated';
    }
}

if ($redirect) {
    echo "<script>window.location.href='{$redirect}';</script>";
    exit;
}

// --- ПОЛУЧЕНИЕ ДАННЫХ ---
$stmt = $pdo->query("
    SELECT * FROM glass_compatibility 
    ORDER BY brand ASC, 
    CAST(REGEXP_REPLACE(main_model, '[^0-9]', '') AS UNSIGNED) DESC,
    main_model ASC
");
$all_items = $stmt->fetchAll();

$grouped = []; 
foreach ($all_items as $item) { 
    $grouped[$item['brand']][] = $item; 
}

if ($isAdmin) {
    $suggestions = $pdo->query("
        SELECT s.*, u.first_name, u.last_name 
        FROM glass_suggestions s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.status = 'pending' 
        ORDER BY s.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM glass_suggestions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    $suggestions = $stmt->fetchAll();
}

$active_tab = $_GET['tab'] ?? 'base';
$success_msg = $_GET['success'] ?? null;
?>

<style>
:root {
    --primary: #8b5cf6;
    --primary-dark: #7c3aed;
    --success: #86efac;
    --danger: #ef4444;
    --warning: #fbbf24;
    --bg-card: rgba(255,255,255,0.02);
    --bg-input: rgba(255,255,255,0.05);
    --border: rgba(255,255,255,0.1);
    --text: #e4e4e7;
    --text-bright: #fafafa;
    --text-dim: rgba(255,255,255,0.5);
}

* { box-sizing: border-box; }

.glass-layout { 
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif; 
    color: var(--text); 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 20px; 
}

.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 20px; flex-wrap: wrap; }
.page-title { margin: 0; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px; color: var(--text-bright); }
.page-title svg { width: 32px; height: 32px; fill: var(--primary); }
.header-actions { display: flex; gap: 12px; align-items: center; }

.search-bar, .form-input { 
    background: var(--bg-input); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    padding: 10px 16px; 
    color: var(--text-bright); 
    outline: none; 
    font-size: 14px;
    transition: all 0.2s;
}
.search-bar { min-width: 280px; }
.search-bar:focus, .form-input:focus { background: rgba(255,255,255,0.08); border-color: var(--primary); }
.search-bar::placeholder, .form-input::placeholder { color: var(--text-dim); }

.btn-primary, .btn-submit { 
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
    color: #fff; 
    border: none; 
    padding: 10px 20px; 
    border-radius: 12px; 
    font-weight: 600; 
    cursor: pointer; 
    font-size: 14px; 
    transition: all 0.2s;
}
.btn-primary { white-space: nowrap; box-shadow: 0 2px 8px rgba(139,92,246,0.3); }
.btn-primary:hover, .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(139,92,246,0.4); }

.tabs { display: flex; gap: 8px; border-bottom: 2px solid rgba(255,255,255,0.05); margin-bottom: 24px; }
.tab-link { 
    padding: 12px 20px; 
    background: none; 
    border: none; 
    color: var(--text-dim); 
    cursor: pointer; 
    font-weight: 600; 
    text-decoration: none; 
    font-size: 13px; 
    text-transform: uppercase; 
    transition: all 0.2s;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}
.tab-link:hover { color: rgba(255,255,255,0.8); }
.tab-link.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-badge { background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; margin-left: 6px; }

.brand-block { margin-bottom: 32px; }
.brand-header { 
    font-size: 11px; 
    font-weight: 800; 
    color: #a78bfa; 
    text-transform: uppercase; 
    letter-spacing: 1.5px; 
    margin-bottom: 12px; 
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(167,139,250,0.2);
}

.glass-card, .suggest-card { 
    background: var(--bg-card); 
    border: 1px solid rgba(255,255,255,0.08); 
    border-radius: 16px; 
    overflow: hidden;
    transition: all 0.2s;
}
.glass-card:hover { border-color: rgba(139,92,246,0.3); background: rgba(255,255,255,0.03); }

.glass-table { width: 100%; border-collapse: collapse; }
.glass-table thead th { background: var(--bg-card); padding: 12px 16px; text-align: left; font-size: 10px; color: var(--text-dim); text-transform: uppercase; font-weight: 700; }
.glass-table tbody td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; vertical-align: middle; }
.glass-table tbody tr:last-child td { border-bottom: none; }
.glass-table tbody tr:hover td { background: rgba(139,92,246,0.08); }

.model-name { font-weight: 700; color: var(--text-bright); }
.compatible-list, .suggest-compat { color: var(--success); font-family: monospace; font-size: 13px; }

.actions-cell { text-align: right; white-space: nowrap; width: 1%; }
.btn-edit, .btn-delete { cursor: pointer; font-weight: 600; font-size: 12px; padding: 6px 12px; border-radius: 8px; transition: all 0.2s; }
.btn-edit { color: var(--primary); background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.2); margin-right: 8px; }
.btn-edit:hover { background: rgba(139,92,246,0.2); }
.btn-delete { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--danger); padding: 6px 10px; }
.btn-delete:hover { background: rgba(239,68,68,0.2); }

.suggest-card { background: rgba(139,92,246,0.05); border-color: rgba(139,92,246,0.15); padding: 20px; margin-bottom: 16px; }
.suggest-card:hover { border-color: rgba(139,92,246,0.3); background: rgba(139,92,246,0.08); }
.suggest-meta { font-size: 12px; color: var(--text-dim); margin-bottom: 12px; display: flex; justify-content: space-between; }
.suggest-brand { font-weight: 700; font-size: 16px; color: var(--text-bright); margin-bottom: 6px; }

.status-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 8px; }
.status-pending { background: rgba(251,191,36,0.15); color: var(--warning); border: 1px solid rgba(251,191,36,0.3); }
.status-approved { background: rgba(134,239,172,0.15); color: var(--success); border: 1px solid rgba(134,239,172,0.3); }
.status-rejected { background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }

.suggest-actions { display: flex; gap: 12px; margin-top: 16px; }
.btn-approve, .btn-reject { border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; flex: 1; font-size: 13px; transition: all 0.2s; color: white; }
.btn-approve { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.btn-reject { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); }
.btn-approve:hover, .btn-reject:hover { transform: translateY(-1px); }

.modal-backdrop { 
    display: none; 
    position: fixed; 
    inset: 0; 
    background: rgba(0,0,0,0.85); 
    z-index: 9999; 
    align-items: center; 
    justify-content: center; 
    backdrop-filter: blur(8px);
}
.modal-box { background: #18181b; padding: 32px; border-radius: 24px; width: 90%; max-width: 480px; border: 1px solid rgba(139,92,246,0.3); }
.modal-title { margin: 0 0 24px; font-size: 22px; font-weight: 700; color: var(--text-bright); }

.form-input { width: 100%; margin-bottom: 16px; font-family: inherit; }
.form-textarea { min-height: 80px; resize: vertical; }
.modal-actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-cancel { flex: 1; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-dim); padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; }
.btn-cancel:hover { background: rgba(255,255,255,0.08); }
.btn-submit { flex: 2; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-dim); }
.empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.3; }

.success-alert { background: rgba(134,239,172,0.1); border: 1px solid rgba(134,239,172,0.3); color: var(--success); padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 500; }

@media (max-width: 768px) {
    .page-header, .header-actions { flex-direction: column; align-items: stretch; }
    .search-bar { min-width: 100%; }
    .glass-table { font-size: 13px; }
    .glass-table thead { display: none; }
    .glass-table tbody td { display: block; padding: 8px 16px; }
    .actions-cell { text-align: left; padding-top: 12px !important; }
}
</style>

<div class="glass-layout">
    <?php if($success_msg): ?>
        <div class="success-alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php
                $messages = [
                    'deleted' => 'Запись успешно удалена',
                    'saved' => 'Изменения сохранены',
                    'sent' => 'Предложение отправлено на модерацию',
                    'moderated' => 'Запрос обработан'
                ];
                echo $messages[$success_msg] ?? 'Операция выполнена';
            ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            База совместимости
        </h1>
        <div class="header-actions">
            <input type="text" id="glassSearch" class="search-bar" placeholder="🔍 Поиск по моделям..." onkeyup="smartSearch()">
            <button class="btn-primary" onclick="openModal()">
                <?= $isAdmin ? '+ Добавить запись' : '💡 Предложить' ?>
            </button>
        </div>
    </div>

    <div class="tabs">
        <a href="?page=glass&tab=base" class="tab-link <?= $active_tab === 'base' ? 'active' : '' ?>">
            База знаний
        </a>
        <a href="?page=glass&tab=requests" class="tab-link <?= $active_tab === 'requests' ? 'active' : '' ?>">
            <?= $isAdmin ? 'Запросы' : 'Мои предложения' ?>
            <?php if($isAdmin && count($suggestions) > 0): ?>
                <span class="tab-badge"><?= count($suggestions) ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if($active_tab === 'base'): ?>
        <?php if(empty($grouped)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                <p>База данных пуста. Добавьте первую запись!</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $brandName => $rows): ?>
                <div class="brand-block">
                    <div class="brand-header"><?= h($brandName) ?></div>
                    <div class="glass-card">
                        <table class="glass-table">
                            <thead>
                                <tr>
                                    <th style="width:25%;">Модель</th>
                                    <th>Совместимые модели</th>
                                    <?php if($isAdmin): ?>
                                        <th style="width:1%;"></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $item): ?>
                                    <tr class="glass-row" data-search="<?= strtolower($item['brand'].' '.$item['main_model'].' '.$item['compatible_list']) ?>">
                                        <td class="model-name"><?= h($item['main_model']) ?></td>
                                        <td class="compatible-list"><?= str_replace('/', ' • ', h($item['compatible_list'])) ?></td>
                                        <?php if($isAdmin): ?>
                                            <td class="actions-cell">
                                                <button class="btn-edit" onclick='editGlass(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Изменить</button>
                                                <form method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить эту запись?')" style="display:inline;">
                                                    <input type="hidden" name="glass_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="delete_glass" class="btn-delete">✕</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <?php if(empty($suggestions)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p><?= $isAdmin ? 'Новых запросов нет' : 'Вы еще не отправляли предложений' ?></p>
            </div>
        <?php else: ?>
            <?php foreach($suggestions as $s): ?>
                <div class="suggest-card">
                    <div class="suggest-meta">
                        <?php if($isAdmin): ?>
                            <span>От: <strong><?= h($s['first_name'] . ' ' . $s['last_name']) ?></strong></span>
                        <?php else: ?>
                            <span class="status-badge status-<?= $s['status'] ?>"><?= h($s['status']) ?></span>
                        <?php endif; ?>
                        <span><?= date('d.m.Y H:i', strtotime($s['created_at'])) ?></span>
                    </div>
                    <div class="suggest-content">
                        <div class="suggest-brand"><?= h($s['brand']) ?> <?= h($s['main_model']) ?></div>
                        <div class="suggest-compat"><?= str_replace('/', ' • ', h($s['compatible_list'])) ?></div>
                    </div>
                    <?php if($isAdmin && $s['status'] === 'pending'): ?>
                        <form method="POST" class="suggest-actions">
                            <input type="hidden" name="suggest_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="moderate_suggest" value="approved" class="btn-approve">✓ Принять</button>
                            <button type="submit" name="moderate_suggest" value="rejected" class="btn-reject">✕ Отклонить</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="addGlassModal" class="modal-backdrop" onclick="if(event.target === this) closeModal()">
    <div class="modal-box">
        <h2 class="modal-title" id="modalTitle"><?= $isAdmin ? 'Добавить модель' : 'Предложить совместимость' ?></h2>
        <form method="POST">
            <input type="hidden" name="glass_id" id="f_glass_id">
            <input type="text" name="brand" id="f_brand" class="form-input" placeholder="Бренд (Apple, Samsung, Xiaomi...)" required>
            <input type="text" name="main_model" id="f_main_model" class="form-input" placeholder="Основная модель (например: 16 Pro Max)" required>
            <textarea name="compatible_list" id="f_compatible_list" class="form-input form-textarea" placeholder="Совместимые модели через слэш (например: 16 Pro/16/15 Pro Max)" required></textarea>
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn-cancel">Отмена</button>
                <button type="submit" name="<?= $isAdmin ? 'save_glass' : 'send_suggestion' ?>" class="btn-submit">
                    <?= $isAdmin ? '💾 Сохранить' : '📤 Отправить' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "<?= $isAdmin ? 'Добавить модель' : 'Предложить совместимость' ?>";
    document.getElementById('f_glass_id').value = "";
    document.getElementById('f_brand').value = "";
    document.getElementById('f_main_model').value = "";
    document.getElementById('f_compatible_list').value = "";
    document.getElementById('addGlassModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function editGlass(data) {
    document.getElementById('modalTitle').innerText = "Редактировать запись";
    document.getElementById('f_glass_id').value = data.id;
    document.getElementById('f_brand').value = data.brand;
    document.getElementById('f_main_model').value = data.main_model;
    document.getElementById('f_compatible_list').value = data.compatible_list;
    document.getElementById('addGlassModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() { 
    document.getElementById('addGlassModal').style.display = 'none';
    document.body.style.overflow = '';
}

function smartSearch() {
    let input = document.getElementById('glassSearch').value.toLowerCase().trim();
    let rows = document.getElementsByClassName('glass-row');
    let blocks = document.getElementsByClassName('brand-block');
    
    if (input === '') {
        for (let row of rows) {
            row.style.display = "";
        }
        for (let block of blocks) {
            block.style.display = "";
        }
        return;
    }
    
    for (let row of rows) {
        let text = row.getAttribute('data-search');
        row.style.display = text.includes(input) ? "" : "none";
    }
    
    for (let block of blocks) {
        let visibleRows = block.querySelectorAll('.glass-row:not([style*="display: none"])');
        block.style.display = (visibleRows.length > 0) ? "" : "none";
    }
}

// Закрытие модального окна по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Автоматическое скрытие уведомления об успехе
setTimeout(function() {
    let alert = document.querySelector('.success-alert');
    if (alert) {
        alert.style.transition = 'opacity 0.3s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }
}, 4000);
</script>
