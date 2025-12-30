<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_role('Owner'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_perms'])) {
    $role_id = (int)$_POST['role_id'];
    
    $checkOwner = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $checkOwner->execute([$role_id]);
    if ($checkOwner->fetchColumn() === 'Owner') {
        echo "<script>alert('Права Владельца изменить нельзя!'); window.location.href='?page=roles';</script>";
        exit;
    }

    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
    if (!empty($_POST['selected_perms'])) {
        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($_POST['selected_perms'] as $p_id) { 
            $stmt->execute([$role_id, (int)$p_id]); 
        }
    }
    echo "<script>window.location.href='?page=roles&edit_role=$role_id&success=1';</script>";
    exit;
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY slug ASC")->fetchAll();

// Группировка с ЧЕЛОВЕЧЕСКИМИ описаниями
$groups = [
    '👥 Персонал и Юзеры' => [
        'manage_users'      => ['title' => 'Список сотрудников', 'desc' => 'Доступ к users.php'],
        'users_pending'     => ['title' => 'Заявки на регистрацию', 'desc' => 'Доступ к users_pending.php'],
        'user_edit'         => ['title' => 'Редактирование профиля', 'desc' => 'Доступ к user_edit.php'],
        'shifts'            => ['title' => 'График смен (Просмотр)', 'desc' => 'Доступ к shifts.php'],
        'shift_add'         => ['title' => 'Окно добавления смены', 'desc' => 'Доступ к shift_add.php'],
        'shift_create'      => ['title' => 'Action: Создание смены', 'desc' => 'Запись новой смены в БД'],
        'shift_delete'      => ['title' => 'Action: Удаление смены', 'desc' => 'Удаление записи из графика'],
        'settings_checkin'  => ['title' => 'Настройка Check-in', 'desc' => 'Доступ к settings_checkin.php'],
        'contacts'          => ['title' => 'Контакты', 'desc' => 'Доступ к contacts.php']
    ],
    '📦 Склад и Цены' => [
        'products'          => ['title' => 'Список товаров', 'desc' => 'Доступ к products.php'],
        'import'            => ['title' => 'Импорт данных', 'desc' => 'Доступ к import.php'],
        'price_revaluation' => ['title' => 'Переоценка', 'desc' => 'Доступ к price_revaluation.php'],
        'price_confirm'     => ['title' => 'Подтверждение цен', 'desc' => 'Доступ к price_confirm.php'],
        'price_log'         => ['title' => 'Лог изменений цен', 'desc' => 'Доступ к price_log.php']
    ],
    '🛒 Продажи и Касса' => [
        'sales_all'         => ['title' => 'Все продажи', 'desc' => 'Доступ к sales_all.php'],
        'sale_view'         => ['title' => 'Просмотр чека', 'desc' => 'Доступ к sale_view.php'],
        'sale_item_add'     => ['title' => 'Action: Добавить товар', 'desc' => 'Добавление позиции в чек']
    ],
    '📈 Отчеты и Графики' => [
        'report_sales'      => ['title' => 'Отчет по продажам', 'desc' => 'Доступ к report_sales.php'],
        'report_sales_checks' => ['title' => 'Отчет по чекам', 'desc' => 'Доступ к report_sales_checks.php'],
        'report_sales_chart'  => ['title' => 'График продаж', 'desc' => 'Доступ к report_sales_chart.php'],
        'report_sales_user_chart' => ['title' => 'График по юзерам', 'desc' => 'Доступ к report_sales_user_chart.php'],
        'kpi_chart'         => ['title' => 'KPI Графики', 'desc' => 'Доступ к kpi_chart.php']
    ],
    '💰 KPI и Зарплата' => [
        'kpi'               => ['title' => 'Общий KPI', 'desc' => 'Доступ к kpi.php'],
        'kpi_branch'        => ['title' => 'KPI Филиалов', 'desc' => 'Доступ к kpi_branch.php'],
        'kpi_user'          => ['title' => 'KPI Сотрудников', 'desc' => 'Доступ к kpi_user.php'],
        'kpi_calculate'     => ['title' => 'Action: Расчет KPI', 'desc' => 'Запуск формул расчета'],
        'kpi_bonus'         => ['title' => 'Бонусы (Управление)', 'desc' => 'Доступ к kpi_bonus.php'],
        'kpi_bonuses'       => ['title' => 'История бонусов', 'desc' => 'Доступ к kpi_bonuses.php'],
        'kpi_plans'         => ['title' => 'Планы продаж', 'desc' => 'Доступ к kpi_plans.php'],
        'kpi_plan_save'     => ['title' => 'Action: Сохранить план', 'desc' => 'Запись плана в БД'],
        'kpi_plan_delete'   => ['title' => 'Action: Удалить план', 'desc' => 'Удаление плана из БД'],
        'kpi_settings'      => ['title' => 'Настройки KPI', 'desc' => 'Доступ к kpi_settings.php'],
        'kpi_fix'           => ['title' => 'Фиксация месяца', 'desc' => 'Доступ к kpi_fix.php'],
        'kpi_fix_save'      => ['title' => 'Action: Сохранить фиксацию', 'desc' => 'Архивация данных месяца'],
        'salary_categories' => ['title' => 'Категории ЗП', 'desc' => 'Доступ к salary_categories.php'],
        'kpi_export_excel'  => ['title' => 'Экспорт Excel', 'desc' => 'Доступ к kpi_export_excel.php'],
        'kpi_export_pdf'    => ['title' => 'Экспорт PDF', 'desc' => 'Доступ к kpi_export_pdf.php'],
        'kpi_export_csv'    => ['title' => 'Экспорт CSV', 'desc' => 'Доступ к kpi_export_csv.php']
    ],
    '🏢 Сеть и Админ' => [
        'branches'          => ['title' => 'Список филиалов', 'desc' => 'Доступ к branches.php'],
        'branch_save'       => ['title' => 'Action: Сохранить филиал', 'desc' => 'Создание/Правка филиала'],
        'branch_delete'     => ['title' => 'Action: Удалить филиал', 'desc' => 'Удаление филиала'],
        'roles'             => ['title' => 'Управление ролями', 'desc' => 'Доступ к roles.php']
    ]
];

$selected_role_id = isset($_GET['edit_role']) ? (int)$_GET['edit_role'] : null;
$active_perms = $selected_role_id ? $pdo->query("SELECT permission_id FROM role_permissions WHERE role_id = $selected_role_id")->fetchAll(PDO::FETCH_COLUMN) : [];
?>

<style>
    .roles-layout { display: flex; gap: 30px; align-items: flex-start; font-family: 'Inter', sans-serif; }
    .roles-sidebar { width: 320px; flex-shrink: 0; position: sticky; top: 20px; }
    .roles-main { flex: 1; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 30px; padding: 40px; }
    
    .role-card { 
        display: block; padding: 20px; margin-bottom: 12px; border-radius: 20px; 
        background: rgba(255,255,255,0.03); text-decoration: none; color: #eee; 
        border: 1px solid rgba(255,255,255,0.05); transition: 0.3s;
    }
    .role-card:hover { background: rgba(120, 90, 255, 0.1); transform: translateX(8px); }
    .role-card.active { background: rgba(120, 90, 255, 0.15); border-color: #785aff; box-shadow: 0 15px 30px rgba(120, 90, 255, 0.1); }
    
    .group-block { margin-bottom: 40px; }
    .group-title { 
        color: #b866ff; font-size: 14px; font-weight: 800; text-transform: uppercase; 
        letter-spacing: 2px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }
    
    .perms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
    
    .perm-card { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); 
        padding: 15px; border-radius: 18px; cursor: pointer; display: flex; gap: 15px; align-items: flex-start; transition: 0.2s;
    }
    .perm-card:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
    .perm-card.active { border-color: rgba(120, 90, 255, 0.4); background: rgba(120, 90, 255, 0.05); }

    .perm-info b { display: block; font-size: 14px; color: #fff; margin-bottom: 4px; }
    .perm-info span { display: block; font-size: 11px; color: rgba(255,255,255,0.4); line-height: 1.4; }

    .save-bar { 
        position: sticky; bottom: 20px; background: #785aff; color: #fff; 
        border: none; padding: 20px; border-radius: 20px; width: 100%; 
        font-weight: 800; font-size: 16px; cursor: pointer; margin-top: 30px;
        box-shadow: 0 10px 40px rgba(120, 90, 255, 0.4); transition: 0.3s;
    }
    .save-bar:hover { transform: translateY(-3px); background: #6344d4; }
    
    input[type="checkbox"] { width: 20px; height: 20px; accent-color: #785aff; margin-top: 3px; }
</style>

<div class="roles-layout">
    <div class="roles-sidebar">
        <h2 style="margin-bottom: 25px; margin-left: 10px;">🎭 Роли</h2>
        <?php foreach ($roles as $r): ?>
            <a href="?page=roles&edit_role=<?= $r['id'] ?>" class="role-card <?= $selected_role_id == $r['id'] ? 'active' : '' ?>">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <b><?= htmlspecialchars($r['name']) ?></b>
                    <span><?= $r['name'] === 'Owner' ? '🔒' : '⚙️' ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="roles-main">
        <?php if ($selected_role_id): 
            $role_name = ''; foreach($roles as $role) if($role['id'] == $selected_role_id) $role_name = $role['name'];
        ?>
            <h1 style="margin-top:0; margin-bottom: 10px;">Доступы: <span style="color:#b866ff"><?= $role_name ?></span></h1>
            <p class="muted" style="margin-bottom: 40px;">Выберите действия, которые разрешены данной роли в системе.</p>

            <form method="POST">
                <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
                <input type="hidden" name="save_perms" value="1">

                <?php foreach ($groups as $group_name => $items): ?>
                    <div class="group-block">
                        <div class="group-title"><?= $group_name ?></div>
                        <div class="perms-grid">
                            <?php foreach ($all_permissions as $p): 
                                if (isset($items[$p['slug']])): 
                                    $info = $items[$p['slug']];
                                    $isChecked = in_array($p['id'], $active_perms);
                            ?>
                                <label class="perm-card <?= $isChecked ? 'active' : '' ?>">
                                    <input type="checkbox" name="selected_perms[]" value="<?= $p['id'] ?>" 
                                           <?= $isChecked ? 'checked' : '' ?>
                                           <?= $role_name === 'Owner' ? 'disabled checked' : '' ?>>
                                    <div class="perm-info">
                                        <b><?= $info['title'] ?></b>
                                        <span><?= $info['desc'] ?></span>
                                    </div>
                                </label>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($role_name !== 'Owner'): ?>
                    <button type="submit" class="save-bar">💾 СОХРАНИТЬ ВСЕ ПРАВА</button>
                <?php else: ?>
                    <div style="background: rgba(120, 90, 255, 0.1); border: 1px dashed #785aff; padding: 25px; border-radius: 20px; text-align: center; color: #b866ff;">
                        👑 <b>У Владельца полный доступ.</b> Эти настройки нельзя изменить для безопасности системы.
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 100px 0; opacity: 0.2;">
                <div style="font-size: 80px;">🛡️</div>
                <h2>Выберите роль слева</h2>
                <p>чтобы настроить права доступа для сотрудников</p>
            </div>
        <?php endif; ?>
    </div>
</div>