<?php
// Подключаем ядро системы
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../perms.php';

$user = current_user();
$page = $_GET['page'] ?? 'dashboard';
$area = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'admin' : 'cabinet';

// Логика: новые цены
$unconfirmed_id = null;
if (!empty($user['id'])) {
    $stmt = $pdo->prepare("
        SELECT r.id FROM price_revaluations r
        WHERE r.id NOT IN (SELECT revaluation_id FROM price_revaluation_confirmations WHERE user_id = ?)
        ORDER BY r.id DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $unconfirmed_id = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KUB — CRM System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css">
<link rel="stylesheet" href="/assets/css/layout.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/layout.css') ?>">
<link rel="stylesheet" href="/assets/css/chat.css?v=<?= time() ?>">
<style>
    /* Стиль для индикатора в шапке */
    .top-nav-icons { display: flex; align-items: center; gap: 20px; }
    .chat-link { position: relative; text-decoration: none; font-size: 20px; transition: 0.3s; }
    .chat-link:hover { transform: scale(1.1); }
    .unread-dot { 
        position: absolute; top: -2px; right: -5px; 
        width: 10px; height: 10px; background: #ff3b30; 
        border-radius: 50%; border: 2px solid #1c212c; 
        display: none; /* Появляется через JS */
    }
</style>
</head>
<body>

<?php if ($unconfirmed_id && $page !== 'price_confirm'): ?>
<div class="modal-overlay">
    <div class="modal-box">
        <div class="modal-icon">📢</div>
        <h2>Внимание: Новые цены!</h2>
        <p>Администратор обновил стоимость товаров. Нужно подтверждение.</p>
        <a href="?page=price_confirm&id=<?= (int)$unconfirmed_id ?>" class="modal-btn">👀 Просмотреть изменения</a>
    </div>
</div>
<?php endif; ?>

<div class="wrap" id="global-app-root" data-user-id="<?= (int)($user['id'] ?? 0) ?>">
    <span id="global-user-info" data-id="<?= (int)($user['id'] ?? 0) ?>" style="display:none;"></span>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="content">
        <div class="page-container">
            <div class="top-bar">
                <div class="badge"><?= $area === 'admin' ? 'SYSTEM ADMINISTRATION' : 'EMPLOYEE CABINET' ?></div>
                
                <div class="top-nav-icons">
                    <a href="/cabinet/index.php?page=chat" class="chat-link" title="Открыть чат">
                        💬 <span id="chat-unread-dot" class="unread-dot"></span>
                    </a>
                    <div class="date"><?= date('d.m.Y') ?></div>
                </div>
            </div>