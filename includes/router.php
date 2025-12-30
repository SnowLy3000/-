<?php
// /includes/router.php

/**
 * Получение значения из GET запроса
 */
function q(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/* ================= ЛИЧНЫЙ КАБИНЕТ (CABINET) ================= */
function cabinet_pages(): array
{
    return [
        'dashboard'     => __DIR__ . '/../cabinet/pages/dashboard.php',
        'schedule'      => __DIR__ . '/../cabinet/pages/schedule.php',
        'checkin'       => __DIR__ . '/../cabinet/pages/checkin.php',
        'transfers'     => __DIR__ . '/../cabinet/pages/transfers.php',
        'sales'         => __DIR__ . '/../cabinet/pages/sales.php',
        'sales_history' => __DIR__ . '/../cabinet/pages/sales_history.php',
        'sale_view'     => __DIR__ . '/../cabinet/pages/sale_view.php',
        'kpi'           => __DIR__ . '/../cabinet/pages/kpi.php', // Личный KPI сотрудника
        'profile'       => __DIR__ . '/../cabinet/pages/profile.php',
        'price_confirm' => __DIR__ . '/../cabinet/pages/price_confirm.php',         
    ];
}
/* ================= АДМИН-ПАНЕЛЬ (ADMIN) ================= */
function admin_pages(): array
{
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

        // Продажи и отчетность
        'sales_all'            => __DIR__ . '/../admin/pages/sales_all.php',
        'sale_view'            => __DIR__ . '/../admin/pages/sale_view.php',
        'report_sales_chart'      => __DIR__ . '/../admin/pages/report_sales_chart.php',
        'report_sales_checks'     => __DIR__ . '/../admin/pages/report_sales_checks.php',
        'report_sales_user_chart' => __DIR__ . '/../admin/pages/report_sales_user_chart.php',
        'report_sales'         => __DIR__ . '/../admin/pages/report_sales.php',

        // Система KPI и Зарплаты
        'kpi'                  => __DIR__ . '/../admin/pages/kpi.php', 
        'kpi_branch'           => __DIR__ . '/../admin/pages/kpi_branch.php',
        'kpi_user'             => __DIR__ . '/../admin/pages/kpi_user.php',
        'kpi_bonus'            => __DIR__ . '/../admin/pages/kpi_bonus.php',   // Ведомость (Текущая)
        'kpi_bonuses'          => __DIR__ . '/../admin/pages/kpi_bonuses.php', // Архив выплат
        'kpi_plans'            => __DIR__ . '/../admin/pages/kpi_plans.php',
        'kpi_fix'              => __DIR__ . '/../admin/pages/kpi_fix.php',
        'kpi_chart'            => __DIR__ . '/../admin/pages/kpi_chart.php',
        'kpi_settings'         => __DIR__ . '/../admin/pages/kpi_settings.php',
        
        // Финансы и товары
        'salary_categories'    => __DIR__ . '/../admin/pages/salary_categories.php',
        'products'             => __DIR__ . '/../admin/pages/products.php',
        'import'               => __DIR__ . '/../admin/pages/import.php',
        
        // Замена цен 
        'price_revaluation' => __DIR__ . '/../admin/pages/price_revaluation.php',
        'price_confirm'     => __DIR__ . '/../admin/pages/price_confirm.php',
        'price_log'         => __DIR__ . '/../admin/pages/price_log.php',
        
        // Роли
        'roles'             => __DIR__ . '/../admin/pages/roles.php',
        
    ];
}
