<?php
// 1. СНАЧАЛА ПОДКЛЮЧАЕМ СИСТЕМУ (Порядок важен!)
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../perms.php'; // Теперь has_role доступна сразу

// 2. ПРЕРЫВАТЕЛЬ ДЛЯ AJAX (Поиск товаров)
if (isset($_GET['search_q']) || isset($_GET['create_new_product'])) {
    $revalFile = __DIR__ . '/../../admin/pages/price_revaluation.php';
    if (file_exists($revalFile)) { require_once $revalFile; }
    exit; 
}

$user = current_user();
$page = $_GET['page'] ?? 'dashboard';
$area = $area ?? 'cabinet'; 

/* ===== ЛОГИКА УВЕДОМЛЕНИЙ О ЦЕНАХ ===== */
$unconfirmed_id = null;
if (isset($user['id'])) {
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
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KUB — CRM System</title>
<style>
    /* ТВОИ СТИЛИ (сохранены полностью) */
    body { margin: 0; font-family: 'Inter', sans-serif; background: #0b0f1a; color: #e8eefc; line-height: 1.6; }
    a { color: #e8eefc; text-decoration: none; }
    .wrap { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #0f1629; padding: 30px 20px; box-sizing: border-box; border-right: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; display: flex; flex-direction: column; }
    .brand { font-size: 24px; font-weight: 900; letter-spacing: 4px; margin-bottom: 30px; color: #785aff; text-align: center; }
    .userbox { padding: 15px; background: rgba(120,90,255,0.05); border: 1px solid rgba(120,90,255,0.15); border-radius: 16px; margin-bottom: 25px; }
    .nav { flex-grow: 1; overflow-y: auto; }
    .nav h4 { margin: 25px 0 10px 15px; font-size: 10px; opacity: .4; text-transform: uppercase; letter-spacing: 2px; }
    .item { display: flex; align-items: center; padding: 12px 15px; border-radius: 12px; margin-bottom: 4px; font-size: 14px; color: rgba(232,238,252,0.6); }
    .item:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .active { background: rgba(120,90,255,0.15); color: #b866ff !important; font-weight: 700; border: 1px solid rgba(120,90,255,0.2); }
    .content { flex: 1; padding: 40px; box-sizing: border-box; display: flex; flex-direction: column; align-items: center; }
    .page-container { width: 100%; max-width: 1100px; }
    .top { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .badge { padding: 6px 12px; border-radius: 8px; background: rgba(120,90,255,0.1); border: 1px solid rgba(120,90,255,0.2); font-size: 10px; color: #785aff; }
    .btn { display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 12px; background: #785aff; color: #fff; font-weight: 600; cursor: pointer; }
</style>
</head>
<body>

<?php if ($unconfirmed_id && $page !== 'price_confirm'): ?>
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 15, 26, 0.98); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(10px);">
        <div style="background: rgba(255,255,255,0.03); padding: 40px; border-radius: 20px; border: 2px solid #785aff; text-align: center; max-width: 500px;">
            <div style="font-size: 50px; margin-bottom: 20px;">📢</div>
            <h2>Внимание: Новые цены!</h2>
            <p style="opacity: 0.6; margin-bottom: 25px;">Администратор обновил стоимость товаров. Нужно подтверждение.</p>
            <a href="?page=price_confirm&id=<?= $unconfirmed_id ?>" class="btn">👀 Просмотреть изменения</a>
        </div>
    </div>
<?php endif; ?>

<div class="wrap">
<aside class="sidebar">
    <div class="brand">KUB</div>
    <div class="userbox">
        <b><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></b>
        <div style="font-size: 11px; opacity: 0.5;">
            <?php
            $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
            $stmt->execute([$user['id']]);
            $rolesList = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo htmlspecialchars(implode(' • ', $rolesList ?: ['Сотрудник']));
            ?>
        </div>
    </div>

    <div class="nav">
<?php if ($area === 'cabinet'): ?>
    <h4>Кабинет</h4>
    <a class="item <?= $page==='dashboard'?'active':'' ?>" href="/cabinet/index.php?page=dashboard">🏠 Dashboard</a>
    <a class="item <?= $page==='schedule'?'active':'' ?>" href="/cabinet/index.php?page=schedule">📅 График работы</a>
    <a class="item <?= $page==='checkin'?'active':'' ?>" href="/cabinet/index.php?page=checkin">📍 Check-in</a>
    <a class="item <?= $page==='transfers'?'active':'' ?>" href="/cabinet/index.php?page=transfers">🤝 Передача смен</a>
    <a class="item <?= $page==='sales'?'active':'' ?>" href="/cabinet/index.php?page=sales">💰 Продажи</a>
    <a class="item <?= $page==='sales_history'?'active':'' ?>" href="/cabinet/index.php?page=sales_history">📖 История</a>
    <a class="item <?= $page==='kpi'?'active':'' ?>" href="/cabinet/index.php?page=kpi">📈 Мой KPI</a>
    <a class="item <?= $page==='profile'?'active':'' ?>" href="/cabinet/index.php?page=profile">👤 Профиль</a>
    
    <?php if (has_role('Admin') || has_role('Owner')): ?>
        <div style="margin-top: 10px; border-top: 1px solid rgba(120, 90, 255, 0.2); padding-top: 10px;">
            <a class="item" href="/admin/index.php?page=dashboard" style="color:#785aff;">⚙️ Админ-панель</a>
        </div>
    <?php endif; ?>

<?php else: ?>
    <h4>Управление</h4>
    <a class="item <?= $page==='dashboard'?'active':'' ?>" href="/admin/index.php?page=dashboard">📊 Главная</a>
    
    <?php if (can_user('contacts')): ?>
        <a class="item <?= $page === 'contacts' ? 'active' : '' ?>" href="/admin/index.php?page=contacts">
            <span>👥 Список контактов</span>
        </a>    
    <?php endif; ?>

    <?php if (can_user('manage_users') || can_user('users_pending') || can_user('branches')): ?>
        <?php if (can_user('manage_users')): ?>
            <a class="item <?= ($page==='users' || $page==='user_edit')?'active':'' ?>" href="/admin/index.php?page=users">🛡️ Сотрудники</a>
        <?php endif; ?>

        <?php if (can_user('settings_checkin')): ?>
            <a class="item <?= $page==='settings_checkin'?'active':'' ?>" href="/admin/index.php?page=settings_checkin">🔧 Настройки Check-in</a>
        <?php endif; ?>
        
        <?php if (can_user('branches')): ?>
            <a class="item <?= $page==='branches'?'active':'' ?>" href="/admin/index.php?page=branches">🏢 Филиалы</a>
        <?php endif; ?>

        <?php if (can_user('users_pending')): ?>
            <a class="item <?= $page==='users_pending'?'active':'' ?>" href="/admin/index.php?page=users_pending">⏳ Заявки</a>
        <?php endif; ?>

        <?php if (can_user('shifts')): ?>
            <a class="item <?= $page==='shifts'?'active':'' ?>" href="/admin/index.php?page=shifts">🗓️ График смен</a>
        <?php endif; ?>

        <?php if (can_user('roles')): ?>
            <a class="item <?= $page==='roles'?'active':'' ?>" href="/admin/index.php?page=roles">🔑 Роли и Доступ</a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (can_user('price_revaluation') || can_user('price_log') || can_user('price_confirm')): ?>
        <h4>Цены и Переоценка</h4>
        <?php if (can_user('price_revaluation')): ?>
            <a class="item <?= $page==='price_revaluation'?'active':'' ?>" href="/admin/index.php?page=price_revaluation">🔄 Новая переоценка</a>
        <?php endif; ?>
        <?php if (can_user('price_log')): ?>
            <a class="item <?= $page==='price_log'?'active':'' ?>" href="/admin/index.php?page=price_log">📜 Журнал изменений</a>
        <?php endif; ?>
        <?php if (can_user('price_confirm')): ?>
            <a class="item <?= $page==='price_confirm'?'active':'' ?>" href="/admin/index.php?page=price_confirm">✅ Подтверждение</a>
        <?php endif; ?>
    <?php endif; ?>

    <h4>Продажи</h4>
    <?php if (can_user('sales_all')): ?>
        <a class="item <?= $page==='sales_all'?'active':'' ?>" href="/admin/index.php?page=sales_all">🧾 Все чеки</a>
    <?php endif; ?>
    <?php if (can_user('report_sales')): ?>
        <a class="item <?= $page==='report_sales'?'active':'' ?>" href="/admin/index.php?page=report_sales">📋 Таблица КПЭ</a>
    <?php endif; ?>
    <?php if (can_user('report_sales_checks')): ?>
        <a class="item <?= $page==='report_sales_checks'?'active':'' ?>" href="/admin/index.php?page=report_sales_checks">🔍 Детализация чеков</a>
    <?php endif; ?>

    <h4>Система KPI</h4>
    <?php if (can_user('kpi')): ?>
        <a class="item <?= $page==='kpi'?'active':'' ?>" href="/admin/index.php?page=kpi">🎯 Аналитика</a>
    <?php endif; ?>
    <?php if (can_user('report_sales_chart')): ?>
        <a class="item <?= $page==='report_sales_chart'?'active':'' ?>" href="/admin/index.php?page=report_sales_chart">📈 График сети</a>
    <?php endif; ?>
    <?php if (can_user('kpi_branch')): ?>
        <a class="item <?= $page==='kpi_branch'?'active':'' ?>" href="/admin/index.php?page=kpi_branch">🏢 По филиалам</a>
    <?php endif; ?>
    <?php if (can_user('kpi_user')): ?>
        <a class="item <?= $page==='kpi_user'?'active':'' ?>" href="/admin/index.php?page=kpi_user">👤 По сотрудникам</a>
    <?php endif; ?>
    <?php if (can_user('kpi_chart')): ?>
        <a class="item <?= $page==='kpi_chart'?'active':'' ?>" href="/admin/index.php?page=kpi_chart">📊 Рейтинг</a>
    <?php endif; ?>
    <?php if (can_user('report_sales_user_chart')): ?>
        <a class="item <?= $page==='report_sales_user_chart'?'active':'' ?>" href="/admin/index.php?page=report_sales_user_chart">📊 Графики продаж</a>
    <?php endif; ?>

    <?php if (can_user('kpi_bonus') || can_user('kpi_bonuses') || can_user('kpi_fix') || can_user('products')): ?>
        <h4>Финансы и База</h4>
        <?php if (can_user('kpi_bonus')): ?>
            <a class="item <?= $page==='kpi_bonus'?'active':'' ?>" href="/admin/index.php?page=kpi_bonus">💵 Ведомость (Тек)</a>
        <?php endif; ?>
        <?php if (can_user('kpi_bonuses')): ?>
            <a class="item <?= $page==='kpi_bonuses'?'active':'' ?>" href="/admin/index.php?page=kpi_bonuses">📒 Архив выплат</a>
        <?php endif; ?>
        <?php if (can_user('kpi_plans')): ?>
            <a class="item <?= $page==='kpi_plans'?'active':'' ?>" href="/admin/index.php?page=kpi_plans">🏁 Планы</a>
        <?php endif; ?>
        <?php if (can_user('kpi_fix')): ?>
            <a class="item <?= $page === 'kpi_fix' ? 'active' : '' ?>" href="/admin/index.php?page=kpi_fix">🔒 Фиксация месяца</a>
        <?php endif; ?>
        <?php if (can_user('kpi_settings')): ?>
            <a class="item <?= $page==='kpi_settings'?'active':'' ?>" href="/admin/index.php?page=kpi_settings">⚙️ Параметры KPI</a>
        <?php endif; ?>
        <?php if (can_user('salary_categories')): ?>
            <a class="item <?= $page==='salary_categories'?'active':'' ?>" href="/admin/index.php?page=salary_categories">💳 Категории ЗП</a>
        <?php endif; ?>
        <?php if (can_user('products')): ?>
            <a class="item <?= $page==='products'?'active':'' ?>" href="/admin/index.php?page=products">📦 Товары</a>
        <?php endif; ?>
        <?php if (can_user('import')): ?>
            <a class="item <?= $page==='import'?'active':'' ?>" href="/admin/index.php?page=import">📥 Импорт Excel</a>
        <?php endif; ?>
    <?php endif; ?>




        
        <a class="item" href="/cabinet/index.php?page=dashboard" style="margin-top:15px; opacity:0.6;">← В кабинет</a>
    <?php endif; ?>
    </div>
    <a class="item" href="/public/logout.php" style="margin-top:20px; color:#ff6b6b">🚪 Выйти</a>
</aside>

<main class="content">
    <div class="page-container">
        <div class="top">
            <div class="badge"><?= $area === 'admin' ? 'SYSTEM ADMINISTRATION' : 'EMPLOYEE CABINET' ?></div>
            <div class="muted"><?= date('d.m.Y') ?></div>
        </div>
