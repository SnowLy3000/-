<?php
require_once __DIR__ . '/../includes/auth_logic.php';
require_once __DIR__ . '/../includes/perms.php';
require_once __DIR__ . '/../includes/db.php'; // Добавили подключение БД

$error = '';
$message = '';

// Обработка LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_login'])) {
    $login    = $_POST['login']    ?? '';
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль';
    } else {
        if (login_user($login, $password)) {
            
            /* === НОВЫЙ БЛОК: ЗАПИСЬ ВХОДА В ЛОГИ === */
            if (isset($_SESSION['user']['id'])) {
                $stmtLog = $pdo->prepare("INSERT INTO user_sessions_log (user_id, action_type, ip_address) VALUES (?, 'login', ?)");
                $stmtLog->execute([$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR']]);
            }
            /* ====================================== */

            if (has_role('Admin') || has_role('Owner') || has_role('Marketing')) {
                header('Location: /admin/index.php?page=dashboard');
            } else {
                header('Location: /cabinet/index.php?page=dashboard');
            }
            exit;
        } else {
            $error = 'Неверные данные или аккаунт еще не одобрен';
        }
    }
}

// Обработка REGISTER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    if ($_POST['password'] !== $_POST['password_confirm']) {
        $error = 'Пароли не совпадают';
    } else {
        register_user($_POST);
        $message = 'Заявка отправлена! Ожидайте активации администратором.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему KUB</title>
    <style>
        :root { --primary: #785aff; --bg: #0f0f12; --card: #1a1a1f; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .auth-container { width: 100%; max-width: 420px; padding: 15px; box-sizing: border-box; }
        .auth-card { background: var(--card); border-radius: 24px; padding: 25px 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; position: relative; }
        h2 { margin: 0 0 5px; font-size: 24px; font-weight: 800; letter-spacing: -1px; }
        p.subtitle { color: rgba(255,255,255,0.4); margin-bottom: 20px; font-size: 13px; }
        .st-input { width: 100%; height: 44px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 12px; color: #fff; margin-bottom: 12px; outline: none; box-sizing: border-box; transition: 0.3s; font-size: 14px; }
        .st-input:focus { border-color: var(--primary); background: rgba(120, 90, 255, 0.05); }
        .btn-main { width: 100%; height: 50px; background: var(--primary); color: #fff; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(120, 90, 255, 0.2); margin-top: 10px; }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(120, 90, 255, 0.3); }
        .toggle-link { text-align: center; margin-top: 20px; font-size: 13px; color: rgba(255,255,255,0.4); }
        .toggle-link span { color: var(--primary); cursor: pointer; font-weight: 600; }
        .error-msg { background: rgba(255, 68, 68, 0.1); color: #ff4444; padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; text-align: center; border: 1px solid rgba(255, 68, 68, 0.2); }
        .success-msg { background: rgba(0, 200, 81, 0.2); color: #00ff66; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; text-align: center; border: 1px solid rgba(0, 200, 81, 0.3); font-weight: 600; }
        .form-row { display: flex; gap: 10px; }
        .form-group { flex: 1; margin-bottom: 12px; }
        .form-group label { display: block; font-size: 11px; color: rgba(255,255,255,0.4); margin-bottom: 4px; margin-left: 5px; }
        .form-group .st-input { margin-bottom: 0; }
        .hidden { display: none; }
        .fade-in { animation: fadeIn 0.4s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <?php if ($error): ?> <div class="error-msg"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
        <?php if ($message): ?> <div class="success-msg" id="success-box">✅ <?= htmlspecialchars($message) ?></div> <?php endif; ?>

        <div id="login-form" class="<?= isset($_POST['action_register']) ? 'hidden' : '' ?>">
            <h2>Вход</h2>
            <p class="subtitle">Добро пожаловать в KUB</p>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action_login" value="1">
                <input type="text" name="login" class="st-input" placeholder="Номер телефона" required>
                <input type="password" name="password" class="st-input" placeholder="Пароль" required>
                <button type="submit" class="btn-main">Войти в систему</button>
            </form>
            <div class="toggle-link">Нет аккаунта? <span onclick="toggleAuth()">Зарегистрироваться</span></div>
        </div>

        <div id="register-form" class="<?= isset($_POST['action_register']) ? '' : 'hidden' ?>">
            <h2>Регистрация</h2>
            <p class="subtitle">Создайте профиль сотрудника</p>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action_register" value="1">
                <div class="form-row">
                    <input name="first_name" class="st-input" placeholder="Имя" required>
                    <input name="last_name" class="st-input" placeholder="Фамилия" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Дата рождения</label>
                        <input type="date" name="birth_date" class="st-input" required>
                    </div>
                    <div class="form-group">
                        <label>Пол</label>
                        <select name="gender" class="st-input">
                            <option value="male">Мужской</option>
                            <option value="female">Женский</option>
                        </select>
                    </div>
                </div>
                <input name="phone" class="st-input" placeholder="Номер телефона (Логин)" required>
                <input name="telegram" class="st-input" placeholder="Ник в Telegram (@username)" required>
                <div class="form-row">
                    <input type="password" name="password" class="st-input" placeholder="Пароль" required>
                    <input type="password" name="password_confirm" class="st-input" placeholder="Повтор" required>
                </div>
                <button type="submit" class="btn-main">Отправить заявку</button>
            </form>
            <div class="toggle-link">Уже есть аккаунт? <span onclick="toggleAuth()">Войти</span></div>
        </div>
    </div>
</div>

<script>
    function toggleAuth() {
        const loginForm = document.getElementById('login-form');
        const regForm = document.getElementById('register-form');
        loginForm.classList.toggle('hidden');
        regForm.classList.toggle('hidden');
        if (!loginForm.classList.contains('hidden')) { loginForm.classList.add('fade-in'); } else { regForm.classList.add('fade-in'); }
    }
</script>
</body>
</html>
