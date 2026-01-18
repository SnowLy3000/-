<?php
// /includes/router.php

function q(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

/* ================= ЛИЧНЫЙ КАБИНЕТ (CABINET) ================= */
function cabinet_pages(): array {
    return [
        'dashboard'     => __DIR__ . '/../cabinet/pages/dashboard.php',
        'schedule'      => __DIR__ . '/../cabinet/pages/schedule.php',
        'checkin'       => __DIR__ . '/../cabinet/pages/checkin.php',
        'transfers'     => __DIR__ . '/../cabinet/pages/transfers.php',
        'sales'         => __DIR__ . '/../cabinet/pages/sales.php',
        'sales_history' => __DIR__ . '/../cabinet/pages/sales_history.php',
        'sale_view'     => __DIR__ . '/../cabinet/pages/sale_view.php',
        'returns'       => __DIR__ . '/../cabinet/pages/returns.php',
        'kpi'           => __DIR__ . '/../cabinet/pages/kpi.php',
        'profile'       => __DIR__ . '/../cabinet/pages/profile.php',
        'price_confirm' => __DIR__ . '/../cabinet/pages/price_confirm.php',
        // КЛИЕНТЫ ДЛЯ СОТРУДНИКА
        'clients'       => __DIR__ . '/../admin/pages/clients.php',  
        // Академия и Тесты
        'academy'       => __DIR__ . '/../cabinet/pages/academy.php',
        'test_view'     => __DIR__ . '/../cabinet/pages/test_view.php', // УБРАЛ .php ИЗ КЛЮЧА
        //Совместимость стекл
        'glass'            => __DIR__ . '/../admin/pages/glass.php',
                // Чат
        'chat' => __DIR__ . '/../admin/pages/chat.php',
    ];
}

/* ================= АДМИН-ПАНЕЛЬ (ADMIN) ================= */
function admin_pages(): array {
    return [
        // Основное управление
        'dashboard'            => __DIR__ . '/../admin/pages/dashboard.php',
        'users'                => __DIR__ . '/../admin/pages/users.php',
        'settings_checkin'     => __DIR__ . '/../admin/pages/settings_checkin.php',
        'users_pending'        => __DIR__ . '/../admin/pages/users_pending.php',
        'shifts'               => __DIR__ . '/../admin/pages/shifts.php',
        'user_edit'            => __DIR__ . '/../admin/pages/user_edit.php',
        'contacts'             => __DIR__ . '/../admin/pages/contacts.php',
        'branches'             => __DIR__ . '/../admin/pages/branches.php',
  
        
                // КЛИЕНТЫ (АДМИН)
        'clients'              => __DIR__ . '/../admin/pages/clients.php',
        'client_history'       => __DIR__ . '/../admin/pages/client_history.php',

        // АКЦИИ И СКИДКИ (НОВОЕ!)
        'promotions'           => __DIR__ . '/../admin/pages/promotions.php',
        // 
        'gamification_hub' =>    __DIR__ . '/../admin/pages/gamification_hub.php',
        'academy_manage' =>      __DIR__ . '/../admin/pages/academy_manage.php',
        'test_create' => __DIR__ . '/../admin/pages/test_create.php',
        'test_edit' => __DIR__ . '/../admin/pages/test_edit.php',
        
        
        // Академия и тесты
'academy_manage'      => __DIR__ . '/../admin/pages/academy_manage.php',
'academy_topic_add'   => __DIR__ . '/../admin/pages/academy_topic_add.php',
'academy_topic_edit'  => __DIR__ . '/../admin/pages/academy_topic_edit.php',
'academy_lesson_add'  => __DIR__ . '/../admin/pages/academy_lesson_add.php',
'academy_lesson_edit' => __DIR__ . '/../admin/pages/academy_lesson_edit.php',
'academy_test_manage' => __DIR__ . '/../admin/pages/academy_test_manage.php',
'academy_test_add' => __DIR__ . '/../admin/pages/academy_test_manage.php',
'academy_stats'       => __DIR__ . '/../admin/pages/academy_stats.php',
        
        // МОНИТОРИНГ И ГРАФИКИ
        'staff_monitor'        => __DIR__ . '/../admin/pages/staff_monitor.php', 
        'branch_schedules'     => __DIR__ . '/../admin/pages/branch_schedules.php',
        'report_late'          => __DIR__ . '/../admin/pages/report_late.php', // Добавлено

        // Продажи и отчетность
        'sales_all'            => __DIR__ . '/../admin/pages/sales_all.php',
        'sale_view'            => __DIR__ . '/../admin/pages/sale_view.php',
        'report_sales_chart'      => __DIR__ . '/../admin/pages/report_sales_chart.php',
        'report_sales_checks'     => __DIR__ . '/../admin/pages/report_sales_checks.php',
        'report_sales_user_chart' => __DIR__ . '/../admin/pages/report_sales_user_chart.php',
        'report_sales'         => __DIR__ . '/../admin/pages/report_sales.php',
        'returns_control'      => __DIR__ . '/../admin/pages/returns_control.php',

        // Система KPI и Зарплаты
        'kpi'                  => __DIR__ . '/../admin/pages/kpi.php', 
        'kpi_branch'           => __DIR__ . '/../admin/pages/kpi_branch.php',
        'kpi_user'             => __DIR__ . '/../admin/pages/kpi_user.php',
        'kpi_bonus'            => __DIR__ . '/../admin/pages/kpi_bonus.php',
        'kpi_bonuses'          => __DIR__ . '/../admin/pages/kpi_bonuses.php',
        'kpi_plans'            => __DIR__ . '/../admin/pages/kpi_plans.php',
        'kpi_fix'              => __DIR__ . '/../admin/pages/kpi_fix.php',
        'kpi_chart'            => __DIR__ . '/../admin/pages/kpi_chart.php',
        'kpi_settings'         => __DIR__ . '/../admin/pages/kpi_settings.php',
        
        // Финансы и товары
        'salary_categories'    => __DIR__ . '/../admin/pages/salary_categories.php',
        'products'             => __DIR__ . '/../admin/pages/products.php',
        'import'               => __DIR__ . '/../admin/pages/import.php',
        
        // Замена цен 
        'price_revaluation'    => __DIR__ . '/../admin/pages/price_revaluation.php',
        'price_confirm'        => __DIR__ . '/../admin/pages/price_confirm.php',
        'price_log'            => __DIR__ . '/../admin/pages/price_log.php',
        
        //Совместимость стекл
        'glass'            => __DIR__ . '/../admin/pages/glass.php',
        
        // Чат
        'chat' => __DIR__ . '/../admin/pages/chat.php',
        
        // Скидка на товар
                'promotions'           => __DIR__ . '/../admin/pages/promotions.php',
                
        // Роли
        'roles'                => __DIR__ . '/../admin/pages/roles.php',
    ];
}
