<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// Защита страницы
require_role('Owner'); 

/**
 * 1. ОБРАБОТКА POST-ДЕЙСТВИЙ
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- СОЗДАНИЕ РОЛИ ---
    if (isset($_POST['add_role'])) {
        $name = trim($_POST['new_role_name']);
        if ($name) {
            $check = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                echo "<script>alert('Роль уже существует!'); window.location.href='?page=roles';</script>";
            } else {
                $pdo->prepare("INSERT INTO roles (name) VALUES (?)")->execute([$name]);
                $new_id = $pdo->lastInsertId();
                echo "<script>window.location.href='?page=roles&edit_role=$new_id';</script>";
            }
            exit;
        }
    }

    // --- УДАЛЕНИЕ РОЛИ ---
    if (isset($_POST['delete_role'])) {
        $role_id = (int)$_POST['delete_role'];
        
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $r_name = $stmt->fetchColumn();

        if (in_array($r_name, ['Owner', 'Admin'])) {
            echo "<script>alert('Системные роли нельзя удалять!'); window.location.href='?page=roles';</script>";
            exit;
        }

        $checkUsers = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
        $checkUsers->execute([$role_id]);
        if ($checkUsers->fetchColumn() > 0) {
            echo "<script>alert('Нельзя удалить роль, пока она назначена сотрудникам!'); window.location.href='?page=roles';</script>";
            exit;
        }

        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
        $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$role_id]);
        
        echo "<script>window.location.href='?page=roles&success=deleted';</script>";
        exit;
    }

    // --- СОЗДАНИЕ ДОЛЖНОСТИ ---
    if (isset($_POST['add_position'])) {
        $name = trim($_POST['new_pos_name']);
        if ($name) {
            $check = $pdo->prepare("SELECT id FROM positions WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                echo "<script>alert('Должность уже существует!'); window.location.href='?page=roles';</script>";
            } else {
                $pdo->prepare("INSERT INTO positions (name) VALUES (?)")->execute([$name]);
                echo "<script>window.location.href='?page=roles';</script>";
            }
            exit;
        }
    }

    // --- УДАЛЕНИЕ ДОЛЖНОСТИ ---
    if (isset($_POST['delete_pos'])) {
        $pos_id = (int)$_POST['delete_pos'];
        $check = $pdo->prepare("SELECT COUNT(*) FROM user_positions WHERE position_id = ?");
        $check->execute([$pos_id]);
        if ($check->fetchColumn() == 0) {
            $pdo->prepare("DELETE FROM positions WHERE id = ?")->execute([$pos_id]);
            echo "<script>window.location.href='?page=roles';</script>";
        } else {
            echo "<script>alert('На этой должности еще есть сотрудники!'); window.location.href='?page=roles';</script>";
        }
        exit;
    }

    // --- СОХРАНЕНИЕ ПРАВ ---
    if (isset($_POST['save_perms'])) {
        $role_id = (int)$_POST['role_id'];
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
        if (!empty($_POST['selected_perms'])) {
            $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($_POST['selected_perms'] as $p_id) { $ins->execute([$role_id, (int)$p_id]); }
        }
        echo "<script>window.location.href='?page=roles&edit_role=$role_id&success=1';</script>";
        exit;
    }
}

// Загрузка данных
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll();
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY slug ASC")->fetchAll();

$selected_role_id = isset($_GET['edit_role']) ? (int)$_GET['edit_role'] : null;
$active_perms = $selected_role_id ? $pdo->query("SELECT permission_id FROM role_permissions WHERE role_id = $selected_role_id")->fetchAll(PDO::FETCH_COLUMN) : [];

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
    '👥 Клиентская база' => [
        'clients'           => ['title' => 'Список клиентов', 'desc' => 'Доступ к просмотру, добавлению и поиску'],
        'client_history'    => ['title' => 'История клиентов', 'desc' => 'Журнал изменений данных покупателей'],
        'promotions'        => ['title' => 'Акции и Скидки', 'desc' => 'Управление маркетинговыми акциями']
    ],
    '🚀 Дисциплина и Контроль' => [
        'staff_monitor'     => ['title' => 'Мониторинг Online', 'desc' => 'Видеть кто сейчас в системе'],
        'branch_schedules'  => ['title' => 'Графики филиалов', 'desc' => 'Установка времени открытия точек'],
        'report_late'       => ['title' => 'Журнал опозданий', 'desc' => 'Просмотр штрафов и опозданий']
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
        'returns_control'   => ['title' => 'Контроль возвратов', 'desc' => 'Просмотр брака и отмен'],
        'sale_item_add'     => ['title' => 'Action: Добавить товар', 'desc' => 'Добавление позиции в чек']
    ],
    '📈 Отчеты и Графики' => [
        'report_sales'      => ['title' => 'Отчет по продажам', 'desc' => 'Доступ к report_sales.php'],
        'report_sales_checks' => ['title' => 'Отчет по чекам', 'desc' => 'Доступ к report_sales_checks.php'],
        'report_sales_chart'  => ['title' => 'График продаж', 'desc' => 'Доступ к report_sales_chart.php'],
        'report_sales_user_chart' => ['title' => 'График по юзерам', 'desc' => 'Доступ к report_sales_user_chart.php'],
        'kpi_chart'         => ['title' => 'KPI Графики', 'desc' => 'Доступ к kpi_chart.php'],
        'view_kpi_general'  => ['title' => 'Общая KPI аналитика', 'desc' => 'Доступ к глобальным графикам']
    ],
    '💰 KPI и Зарплата' => [
        'kpi'               => ['title' => 'Общий KPI', 'desc' => 'Доступ к kpi.php'],
        'kpi_branch'        => ['title' => 'KPI Филиалов', 'desc' => 'Доступ к kpi_branch.php'],
        'kpi_user'          => ['title' => 'KPI Сотрудников', 'desc' => 'Доступ к kpi_user.php'],
        'kpi_calculate'     => ['title' => 'Action: Расчет KPI', 'desc' => 'Запуск формул расчета'],
        'kpi_bonus'         => ['title' => 'Бонусы (Управление)', 'desc' => 'Доступ к kpi_bonus.php'],
        'kpi_bonuses'       => ['title' => 'История бонусов', 'desc' => 'Доступ к kpi_bonuses.php'],
        'kpi_plans'         => ['title' => 'Планы продаж', 'desc' => 'Установка целей'],
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

?>

<style>
    .roles-layout { display: flex; gap: 30px; }
    .roles-sidebar { width: 340px; }
    .roles-main { flex: 1; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 30px; padding: 40px; }
    .side-block { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 24px; padding: 20px; margin-bottom: 25px; }
    .role-card-wrap { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .role-card { flex: 1; display: flex; justify-content: space-between; padding: 14px; border-radius: 12px; background: rgba(255,255,255,0.03); text-decoration: none; color: #eee; border: 1px solid transparent; }
    .role-card.active { background: rgba(120, 90, 255, 0.15); border-color: #785aff; }
    .st-input { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 12px; color: #fff; margin-bottom: 10px; box-sizing: border-box; }
    .btn-add { width: 100%; padding: 12px; background: #785aff; border: none; border-radius: 12px; color: #fff; font-weight: bold; cursor: pointer; }
    .btn-del-mini { background: rgba(255,68,68,0.1); border: none; color: #ff4444; width: 35px; height: 45px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-del-mini:hover { background: #ff4444; color: #fff; }
    .perms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .perm-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 15px; cursor: pointer; display: flex; gap: 10px; }
    .save-bar { position: sticky; bottom: 20px; background: #785aff; color: #fff; border: none; padding: 20px; border-radius: 20px; width: 100%; font-weight: 800; cursor: pointer; margin-top: 30px; }
</style>

<div class="roles-layout">
    <div class="roles-sidebar">
        <div class="side-block">
            <h3 style="color:#b866ff;">🎭 Роли</h3>
            <form method="POST" style="margin-bottom:15px;">
                <input type="text" name="new_role_name" class="st-input" placeholder="Название..." required>
                <button type="submit" name="add_role" class="btn-add">+ Роль</button>
            </form>
            <?php foreach ($roles as $r): ?>
                <div class="role-card-wrap">
                    <a href="?page=roles&edit_role=<?= $r['id'] ?>" class="role-card <?= $selected_role_id == $r['id'] ? 'active' : '' ?>">
                        <span><?= htmlspecialchars($r['name']) ?></span>
                        <small><?= in_array($r['name'], ['Owner','Admin']) ? '🔒' : '' ?></small>
                    </a>
                    <?php if(!in_array($r['name'], ['Owner','Admin'])): ?>
                        <form method="POST" onsubmit="return confirm('Удалить роль?')">
                            <input type="hidden" name="delete_role" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-del-mini">✕</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="side-block">
            <h3 style="color:#b866ff;">💼 Должности</h3>
            <form method="POST" style="margin-bottom:15px;">
                <input type="text" name="new_pos_name" class="st-input" placeholder="Должность..." required>
                <button type="submit" name="add_position" class="btn-add">+ Должность</button>
            </form>
            <?php foreach ($positions as $p): ?>
                <div style="display:flex; gap:8px; margin-bottom:5px;">
                    <div style="flex:1; padding:10px; background:rgba(255,255,255,0.02); border-radius:10px;"><?= htmlspecialchars($p['name']) ?></div>
                    <form method="POST" onsubmit="return confirm('Удалить?')">
                        <input type="hidden" name="delete_pos" value="<?= $p['id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#ff4444; cursor:pointer;">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="roles-main">
        <?php if ($selected_role_id): 
            $role_name = ''; foreach($roles as $role) if($role['id'] == $selected_role_id) $role_name = $role['name'];
        ?>
            <h1>Настройка прав: <?= htmlspecialchars($role_name) ?></h1>
            <form method="POST">
                <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
                <input type="hidden" name="save_perms" value="1">
                <?php foreach ($groups as $group_name => $items): ?>
                    <div style="margin-bottom:25px;">
                        <h4 style="color:#b866ff; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:5px;"><?= $group_name ?></h4>
                        <div class="perms-grid">
                            <?php foreach ($all_permissions as $p): 
                                if (isset($items[$p['slug']])): 
                                    $info = $items[$p['slug']];
                                    $isChecked = in_array($p['id'], $active_perms);
                            ?>
                                <label class="perm-card">
                                    <input type="checkbox" name="selected_perms[]" value="<?= $p['id'] ?>" <?= $isChecked ? 'checked' : '' ?> <?= $role_name === 'Owner' ? 'disabled checked' : '' ?>>
                                    <div style="font-size:12px;"><b><?= htmlspecialchars($info['title']) ?></b><br><span style="opacity:0.4;"><?= htmlspecialchars($info['desc']) ?></span></div>
                                </label>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if($role_name !== 'Owner'): ?>
                    <button type="submit" class="save-bar">💾 СОХРАНИТЬ ПРАВА</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div style="text-align:center; padding:100px; opacity:0.3;"><h2>Выберите роль</h2></div>
        <?php endif; ?>
    </div>
</div>