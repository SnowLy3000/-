<?php
// =========================================================================
// AUTH.PHP - Логика аутентификации
// =========================================================================

// Начинаем сессию, только если она еще не активна.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'config.php';

// Функция для безопасного хеширования пароля
function hash_password($password) {
    // Убедитесь, что SECRET_KEY определен в config.php
    return hash('sha256', $password . SECRET_KEY); 
}

// --- Обработка выхода ---
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('current_theme', '', time() - 3600, '/'); 
    header("Location: index.php");
    exit();
}

// --- Обработка входа (для всех: админы и пользователи) ---
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];
    $hashed_password = hash_password($password);

    // 1. Поиск в таблице админов
    $sql_admin = "SELECT id, login, role FROM admins WHERE login = ? AND password_hash = ?";
    $result_admin = db_query($sql_admin, [$login, $hashed_password], 'ss');
    
    if ($result_admin && $result_admin->num_rows === 1) {
        $user = $result_admin->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login'] = $user['login'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['theme'] = DEFAULT_THEME; 
        header("Location: " . ($user['role'] !== ROLE_USER ? "admin.php" : "index.php"));
        exit();
    }

    // 2. Поиск в таблице пользователей (сотрудников) - Логин по телефону
    $sql_user = "SELECT id, username, phone, date_of_birth, theme, role FROM users WHERE phone = ? AND password_hash = ?";
    $result_user = db_query($sql_user, [$login, $hashed_password], 'ss');
    
    if ($result_user && $result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login'] = $user['phone'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['theme'] = $user['theme'];
        $_SESSION['date_of_birth'] = $user['date_of_birth'];
        header("Location: index.php");
        exit();
    }

    $_SESSION['error_message'] = "Неверный логин (телефон) или пароль.";
    // Перенаправляем на главную страницу, чтобы показать ошибку в модальном окне
    header("Location: index.php?error=login"); 
    exit();
}

// --- Обработка регистрации (только для сотрудников) ---
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $password = $_POST['password'];
    $hashed_password = hash_password($password);

    if (empty($username) || empty($phone) || empty($date_of_birth) || empty($password)) {
        $_SESSION['error_message'] = "Все поля обязательны для заполнения.";
    } elseif (!is_numeric($phone) || strlen($phone) < 6) {
        $_SESSION['error_message'] = "Некорректный номер телефона.";
    } elseif (!strtotime($date_of_birth)) { 
         $_SESSION['error_message'] = "Некорректный формат даты рождения.";
    } else {
        $check_sql = "SELECT id FROM users WHERE phone = ?";
        $check_result = db_query($check_sql, [$phone], 's');
        
        if ($check_result && $check_result->num_rows > 0) {
            $_SESSION['error_message'] = "Ошибка: Пользователь с таким номером телефона уже зарегистрирован.";
        } else {
            // Устанавливаем роль по умолчанию ROLE_USER (из config.php)
            $sql = "INSERT INTO users (username, phone, date_of_birth, password_hash, theme, role) VALUES (?, ?, ?, ?, ?, ?)";
            $affected_rows = db_query($sql, [$username, $phone, $date_of_birth, $hashed_password, DEFAULT_THEME, ROLE_USER], 'ssssss');
            
            if ($affected_rows > 0) {
                $_SESSION['success_message'] = "Регистрация прошла успешно! Вы можете войти, используя ваш номер телефона.";
                header("Location: index.php?success=register");
                exit();
            } else {
                $_SESSION['error_message'] = "Произошла внутренняя ошибка при регистрации.";
            }
        }
    }
    
    header("Location: index.php?error=register");
    exit();
}
// НЕТ ЗАКРЫВАЮЩЕГО ТЕГА ?>