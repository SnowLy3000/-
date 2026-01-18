<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// 1. Проверяем право на управление пользователями
require_role('manage_users');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    exit('User not found');
}

// 1. Получаем данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) exit('User not found');

// 2. Все роли и должности
$roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
$positions = $pdo->query("SELECT * FROM positions ORDER BY name")->fetchAll();

// 3. Текущие роли пользователя
$stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->execute([$id]);
$userRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 4. Текущая должность
$stmt = $pdo->prepare("SELECT position_id FROM user_positions WHERE user_id = ?");
$stmt->execute([$id]);
$userPosId = $stmt->fetchColumn();

// 5. СОХРАНЕНИЕ ДАННЫХ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];

    // Обновляем основные данные
    $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, status='active' WHERE id=?");
    $stmt->execute([$first_name, $last_name, $phone, $id]);

    // Обновляем роли
    $pdo->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$id]);
    if (!empty($_POST['roles'])) {
        $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($_POST['roles'] as $rId) {
            $ins->execute([$id, (int)$rId]);
        }
    }

    // Обновляем должность
    $pdo->prepare("DELETE FROM user_positions WHERE user_id=?")->execute([$id]);
    if (!empty($_POST['position_id'])) {
        $pdo->prepare("INSERT INTO user_positions (user_id, position_id) VALUES (?, ?)")
            ->execute([$id, (int)$_POST['position_id']]);
    }

    echo "<script>window.location.href='/admin/index.php?page=users';</script>";
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .edit-layout { max-width: 600px; margin: 0 auto; font-family: 'Inter', sans-serif; padding: 20px 0 50px 0; color: #fff; }
    
    .form-section {
        background: #16161a; /* Глубокий темный фон */
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 30px;
        border-radius: 28px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    .section-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #b866ff; /* Фиолетовый акцент */
        margin-bottom: 25px;
        font-weight: 800;
        display: block;
    }

    .label-hint { font-size: 11px; color: rgba(255,255,255,0.3); margin-bottom: 8px; display: block; font-weight: 600; }

    .st-input {
        width: 100%; height: 54px;
        background: #0d0d10;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 14px;
        padding: 0 18px;
        color: #fff;
        margin-bottom: 20px;
        box-sizing: border-box;
        font-size: 15px;
        transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; outline: none; background: #111115; }

    .role-label {
        display: flex; align-items: center; gap: 15px;
        padding: 14px 18px; background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 14px; margin-bottom: 10px; cursor: pointer;
        transition: 0.2s;
    }
    .role-label:hover { background: rgba(120, 90, 255, 0.05); border-color: rgba(120, 90, 255, 0.2); }
    .role-label input { width: 18px; height: 18px; accent-color: #785aff; }

    /* Исправление белого фона в SELECT */
    .st-select {
        width: 100%; height: 54px; 
        background: #0d0d10; /* Темный фон */
        border: 1px solid rgba(255, 255, 255, 0.1); 
        border-radius: 14px;
        padding: 0 15px; 
        color: #fff; /* Белый текст */
        font-size: 15px;
        appearance: none;
        cursor: pointer;
    }
    .st-select option {
        background: #16161a; /* Темный фон для выпадающих пунктов */
        color: #fff;
    }

    .btn-save {
        width: 100%; padding: 20px; background: #785aff; color: #fff;
        border: none; border-radius: 20px; font-size: 16px; font-weight: 800;
        cursor: pointer; transition: 0.3s;
        box-shadow: 0 10px 25px rgba(120, 90, 255, 0.3);
    }
    .btn-save:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(120, 90, 255, 0.4); }
</style>



<div class="edit-layout">
    <div style="text-align: center; margin-bottom: 40px;">
        <h2 style="margin:0; font-weight: 900; font-size: 28px;">Настройка профиля</h2>
        <p style="opacity: 0.4; font-size: 14px; margin-top: 5px;">ID сотрудника: #<?= $id ?></p>
    </div>

    <form method="post">
        <div class="form-section">
            <span class="section-title">👤 Личные данные</span>
            
            <label class="label-hint">Имя сотрудника</label>
            <input type="text" name="first_name" value="<?= h($user['first_name']) ?>" class="st-input" required placeholder="Введите имя">
            
            <label class="label-hint">Фамилия сотрудника</label>
            <input type="text" name="last_name" value="<?= h($user['last_name']) ?>" class="st-input" required placeholder="Введите фамилию">
            
            <label class="label-hint">Контактный телефон</label>
            <input type="text" name="phone" value="<?= h($user['phone']) ?>" class="st-input" required placeholder="+373 ...">
        </div>

        <div class="form-section">
            <span class="section-title">🛡️ Права доступа</span>
            <?php foreach ($roles as $r): ?>
                <label class="role-label">
                    <input type="checkbox" name="roles[]" value="<?= $r['id'] ?>"
                        <?= in_array($r['id'], $userRoleIds) ? 'checked' : '' ?>>
                    <span style="font-weight: 600; font-size: 14px;"><?= h($r['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-section">
            <span class="section-title">💼 Официальная должность</span>
            <label class="label-hint">Выберите из списка</label>
            <select name="position_id" class="st-select">
                <option value="" style="color: rgba(255,255,255,0.3);">— Должность не выбрана —</option>
                <?php foreach ($positions as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $userPosId == $p['id'] ? 'selected' : '' ?>>
                        <?= h($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-save">💾 СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
        
        <a href="/admin/index.php?page=users" style="display: block; text-align: center; margin-top: 25px; color: rgba(255,255,255,0.3); text-decoration: none; font-size: 13px; font-weight: 700;">
            ← Назад к списку
        </a>
    </form>
</div>