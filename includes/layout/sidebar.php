<aside class="sidebar">
    <div class="brand">KUB</div>
    
    <div class="userbox">
        <b><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></b>
        <div class="roles-label">
            <?php if (!empty($user['id'])): ?>
                <?php
                $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
                $stmt->execute([$user['id']]);
                $rolesList = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo htmlspecialchars(implode(' • ', $rolesList ?: ['Сотрудник']));
                ?>
            <?php else: ?>
                Гость
            <?php endif; ?>
        </div>

        <?php
        $uid = $user['id'] ?? null;
        $xp_total = 0;
        $current_grade = ['title' => 'Стажер', 'icon' => '🐣'];
        $xp_percent = 0;
        $display_lvl = "1";

        if ($uid) {
            try {
                $xp_query = $pdo->prepare("SELECT SUM(amount) FROM user_xp_log WHERE user_id = ?");
                $xp_query->execute([$uid]);
                $xp_total = (int)$xp_query->fetchColumn();
            } catch (Exception $e) {
                $xp_total = 0;
            }

            $stmt = $pdo->prepare("SELECT * FROM user_grades WHERE min_xp <= ? ORDER BY min_xp DESC LIMIT 1");
            $stmt->execute([$xp_total]);
            $current_grade = $stmt->fetch() ?: ['title' => 'Стажер', 'icon' => '🐣', 'min_xp' => 0];

            $next_grade_query = $pdo->prepare("SELECT min_xp FROM user_grades WHERE min_xp > ? ORDER BY min_xp ASC LIMIT 1");
            $next_grade_query->execute([$xp_total]);
            $next_xp_threshold = $next_grade_query->fetchColumn();

            if ($next_xp_threshold) {
                $prev_xp_threshold = (int)$current_grade['min_xp'];
                $xp_percent = (($xp_total - $prev_xp_threshold) / max(1, $next_xp_threshold - $prev_xp_threshold)) * 100;
                $display_lvl = floor($xp_total / 500) + 1;
            } else {
                $xp_percent = 100;
                $display_lvl = "MAX";
            }
        }
        ?>

        <div class="user-rank-box">
            <div class="rank-header">
                <span class="rank-icon"><?= htmlspecialchars($current_grade['icon']) ?></span>
                <div>
                    <div class="rank-title"><?= htmlspecialchars($current_grade['title']) ?></div>
                    <div class="rank-xp">XP: <?= number_format($xp_total) ?> • LVL <?= $display_lvl ?></div>
                </div>
            </div>
            <div class="xp-bar-bg">
                <div class="xp-bar-fill" style="width: <?= min(100, max(0, $xp_percent)) ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="nav">
        <?php if ($area === 'cabinet'): ?>
            <h4 class="menu-trigger active-trigger" data-target="cab_main">Кабинет <span class="arrow">▼</span></h4>
            <div class="menu-content open" id="cab_main">
                <a class="item <?= $page==='dashboard'?'active':'' ?>" href="/cabinet/index.php?page=dashboard">🏠 Dashboard</a>
                <a class="item <?= $page==='schedule'?'active':'' ?>" href="/cabinet/index.php?page=schedule">📅 График работы</a>
                <a class="item <?= $page==='checkin'?'active':'' ?>" href="/cabinet/index.php?page=checkin">📍 Check-in</a>
                <a class="item <?= $page==='transfers'?'active':'' ?>" href="/cabinet/index.php?page=transfers">🤝 Передача смен</a>
                <a class="item <?= $page==='sales'?'active':'' ?>" href="/cabinet/index.php?page=sales">💰 Продажи</a>
                <a class="item <?= $page==='sales_history'?'active':'' ?>" href="/cabinet/index.php?page=sales_history">📖 История</a>
                <a class="item <?= $page==='returns'?'active':'' ?>" href="/cabinet/index.php?page=returns" style="border-left: 2px solid #ff6b6b; padding-left: 13px;">🔙 Возвраты</a>
                <a class="item <?= $page==='kpi'?'active':'' ?>" href="/cabinet/index.php?page=kpi">📈 Мой KPI</a>
                <a class="item <?= ($page==='academy' || $page==='academy_lesson_view') ? 'active' : '' ?>" href="/cabinet/index.php?page=academy">🎓 Академия KUB</a>
                <a class="item <?= $page==='clients'?'active':'' ?>" href="/cabinet/index.php?page=clients">👥 База клиентов</a>
                <a class="item <?= $page==='glass'?'active':'' ?>" href="/cabinet/index.php?page=glass">🛡️ Совместимость стекол</a>
                <a class="item <?= $page==='profile'?'active':'' ?>" href="/cabinet/index.php?page=profile">👤 Профиль</a>
            </div>

            <?php if (!empty($user['id']) && (has_role('Admin') || has_role('Owner'))): ?>
                <div style="margin-top: 20px; border-top: 1px solid rgba(120, 90, 255, 0.1); padding-top: 10px;">
                    <a class="item" href="/admin/index.php?page=dashboard" style="color:#785aff; font-weight:bold;">⚙️ Админ-панель</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- АДМИНСКОЕ МЕНЮ -->
            <h4 class="menu-trigger" data-target="adm_mgmt">Управление <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_mgmt">
                <a class="item <?= $page==='dashboard'?'active':'' ?>" href="/admin/index.php?page=dashboard">📊 Главная</a>
                <?php if (can_user('contacts')): ?><a class="item <?= $page === 'contacts' ? 'active' : '' ?>" href="/admin/index.php?page=contacts">👥 Список контактов</a><?php endif; ?>
                <?php if (can_user('manage_users')): ?><a class="item <?= ($page==='users' || $page==='user_edit')?'active':'' ?>" href="/admin/index.php?page=users">🛡️ Сотрудники</a><?php endif; ?>
                <?php if (can_user('settings_checkin')): ?><a class="item <?= $page==='settings_checkin'?'active':'' ?>" href="/admin/index.php?page=settings_checkin">🔧 Настройки Check-in</a><?php endif; ?>
                <?php if (can_user('branches')): ?><a class="item <?= $page==='branches'?'active':'' ?>" href="/admin/index.php?page=branches">🏢 Филиалы</a><?php endif; ?>
                <?php if (can_user('users_pending')): ?><a class="item <?= $page==='users_pending'?'active':'' ?>" href="/admin/index.php?page=users_pending">⏳ Заявки</a><?php endif; ?>
                <?php if (can_user('shifts')): ?><a class="item <?= $page==='shifts'?'active':'' ?>" href="/admin/index.php?page=shifts">🗓️ График смен</a><?php endif; ?>
                <?php if (has_role('Admin') || has_role('Owner')): ?>
                    <a class="item <?= $page==='report_late'?'active':'' ?>" href="/admin/index.php?page=report_late">⏰ Журнал опозданий</a>
                <?php endif; ?>
                <?php if (can_user('roles')): ?><a class="item <?= $page==='roles'?'active':'' ?>" href="/admin/index.php?page=roles">🔑 Роли и Доступ</a><?php endif; ?>
            </div>

            <h4 class="menu-trigger" data-target="adm_academy">🎓 Академия <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_academy">
                <a class="item <?= $page==='gamification_hub' ? 'active' : '' ?>" href="/admin/index.php?page=gamification_hub">🎮 Хаб Геймификации</a>
                <a class="item <?= $page==='academy_manage' ? 'active' : '' ?>" href="/admin/index.php?page=academy_manage">📚 Склад тестов</a>
                <a class="item <?= $page==='academy_stats' ? 'active' : '' ?>" href="/admin/index.php?page=academy_stats">📊 Успеваемость</a>
            </div>

            <h4 class="menu-trigger" data-target="adm_clients">👥 База клиентов <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_clients">
                <a class="item <?= $page==='clients'?'active':'' ?>" href="/admin/index.php?page=clients">📋 Список клиентов</a>
                <a class="item <?= $page==='client_history'?'active':'' ?>" href="/admin/index.php?page=client_history">📜 История изменений</a>
            </div>

            <h4 class="menu-trigger" data-target="adm_prices">Цены и Переоценка<span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_prices">
                <?php if (can_user('price_revaluation')): ?><a class="item <?= $page==='price_revaluation'?'active':'' ?>" href="/admin/index.php?page=price_revaluation">🔄 Новая переоценка</a><?php endif; ?>
                <?php if (can_user('price_log')): ?><a class="item <?= $page==='price_log'?'active':'' ?>" href="/admin/index.php?page=price_log">📜 Журнал изменений</a><?php endif; ?>
                <?php if (can_user('price_confirm')): ?><a class="item <?= $page==='price_confirm'?'active':'' ?>" href="/admin/index.php?page=price_confirm">✅ Подтверждение</a><?php endif; ?>
                <?php if (can_user('promotions')): ?><a class="item <?= $page==='promotions'?'active':'' ?>" href="/admin/index.php?page=promotions">🔥 Акции и скидки</a><?php endif; ?>
                <?php if (has_role('Admin') || has_role('Owner')): ?>
                    <a class="item <?= $page==='glass'?'active':'' ?>" href="/admin/index.php?page=glass" style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">🛡️ Совместимость стекол</a>
                <?php endif; ?>
            </div>

            <h4 class="menu-trigger" data-target="adm_sales">Продажи <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_sales">
                <?php if (can_user('sales_all')): ?><a class="item <?= $page==='sales_all'?'active':'' ?>" href="/admin/index.php?page=sales_all">🧾 Все чеки</a><?php endif; ?>
                <?php if (can_user('report_sales')): ?><a class="item <?= $page==='report_sales'?'active':'' ?>" href="/admin/index.php?page=report_sales">📋 Таблица КПЭ</a><?php endif; ?>
                <?php if (can_user('report_sales_checks')): ?><a class="item <?= $page==='report_sales_checks'?'active':'' ?>" href="/admin/index.php?page=report_sales_checks">🔍 Детализация чеков</a><?php endif; ?>
                <?php if (can_user('report_sales_checks') || (int)($_SESSION['user']['id'] ?? 0) === 1): ?>
                    <a class="item <?= $page==='returns_control'?'active':'' ?>" href="/admin/index.php?page=returns_control">🔙 Контроль возвратов</a>
                <?php endif; ?>
            </div>

            <h4 class="menu-trigger" data-target="adm_kpi">Система KPI <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_kpi">
                <?php if (can_user('kpi')): ?><a class="item <?= $page==='kpi'?'active':'' ?>" href="/admin/index.php?page=kpi">🎯 Аналитика</a><?php endif; ?>
                <?php if (can_user('report_sales_chart')): ?><a class="item <?= $page==='report_sales_chart'?'active':'' ?>" href="/admin/index.php?page=report_sales_chart">📈 График сети</a><?php endif; ?>
                <?php if (can_user('kpi_branch')): ?><a class="item <?= $page==='kpi_branch'?'active':'' ?>" href="/admin/index.php?page=kpi_branch">🏢 По филиалам</a><?php endif; ?>
                <?php if (can_user('kpi_user')): ?><a class="item <?= $page==='kpi_user'?'active':'' ?>" href="/admin/index.php?page=kpi_user">👤 По сотрудникам</a><?php endif; ?>
                <?php if (can_user('kpi_chart')): ?><a class="item <?= $page==='kpi_chart'?'active':'' ?>" href="/admin/index.php?page=kpi_chart">📊 Рейтинг</a><?php endif; ?>
                <?php if (can_user('report_sales_user_chart')): ?><a class="item <?= $page==='report_sales_user_chart'?'active':'' ?>" href="/admin/index.php?page=report_sales_user_chart">📊 Графики продаж</a><?php endif; ?>
            </div>

            <h4 class="menu-trigger" data-target="adm_fin">Финансы и База <span class="arrow">▼</span></h4>
            <div class="menu-content" id="adm_fin">
                <?php if (can_user('kpi_bonus')): ?><a class="item <?= $page==='kpi_bonus'?'active':'' ?>" href="/admin/index.php?page=kpi_bonus">💵 Ведомость (Тек)</a><?php endif; ?>
                <?php if (can_user('kpi_bonuses')): ?><a class="item <?= $page==='kpi_bonuses'?'active':'' ?>" href="/admin/index.php?page=kpi_bonuses">📒 Архив выплат</a><?php endif; ?>
                <?php if (can_user('kpi_plans')): ?><a class="item <?= $page==='kpi_plans'?'active':'' ?>" href="/admin/index.php?page=kpi_plans">🏁 Планы</a><?php endif; ?>
                <?php if (can_user('kpi_fix')): ?><a class="item <?= $page === 'kpi_fix' ? 'active' : '' ?>" href="/admin/index.php?page=kpi_fix">🔒 Фиксация месяца</a><?php endif; ?>
                <?php if (can_user('kpi_settings')): ?><a class="item <?= $page==='kpi_settings'?'active':'' ?>" href="/admin/index.php?page=kpi_settings">⚙️ Параметры KPI</a><?php endif; ?>
                <?php if (can_user('salary_categories')): ?><a class="item <?= $page==='salary_categories'?'active':'' ?>" href="/admin/index.php?page=salary_categories">💳 Категории ЗП</a><?php endif; ?>
                <?php if (can_user('products')): ?><a class="item <?= $page==='products'?'active':'' ?>" href="/admin/index.php?page=products">📦 Товары</a><?php endif; ?>
                <?php if (can_user('import')): ?><a class="item <?= $page==='import'?'active':'' ?>" href="/admin/index.php?page=import">📥 Импорт Excel</a><?php endif; ?>
                
                <?php if (has_role('Admin') || has_role('Owner')): ?>
                    <a class="item <?= $page==='staff_monitor'?'active':'' ?>" href="/admin/index.php?page=staff_monitor">🟢 Мониторинг Online</a>
                    <a class="item <?= $page==='branch_schedules'?'active':'' ?>" href="/admin/index.php?page=branch_schedules">🕘 Графики работы</a>
                <?php endif; ?>
            </div>
            
            <a class="item" href="/cabinet/index.php?page=dashboard" style="margin-top:25px; opacity:0.8; border: 1px dashed rgba(120,90,255,0.3);">← Вернуться в кабинет</a>
        <?php endif; ?>
    </div>

    <a class="item" href="/public/logout.php" style="margin-top:auto; color:#ff6b6b; background: rgba(255, 107, 107, 0.05); border: 1px solid rgba(255, 107, 107, 0.1); border-radius: 14px; padding: 12px 18px;">🚪 Выйти из системы</a>
</aside>