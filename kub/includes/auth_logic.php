<?php
$error = null;
$success = null;
$showPending = false;
$pendingUser = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$action = $_POST['action'] ?? '';

/* ================= LOGIN ================= */
if ($action === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль';
        return;
    }

    $user = null;

    // admin / owner
    $st = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $st->execute([$login]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // employee by phone
    if (!$user) {
        $st = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
        $st->execute([$login]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Неверный логин или пароль';
        return;
    }

    if ($user['status'] === 'pending') {
        $showPending = true;
        $pendingUser = $user;
        return;
    }

    if ($user['status'] !== 'active') {
        $error = 'Аккаунт заблокирован';
        return;
    }

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'role' => $user['role'],
        'fullname' => $user['fullname'],
        'theme' => $user['theme'] ?? 'light'
    ];

    header('Location: ' . (in_array($user['role'], ['owner','admin'], true)
        ? '/admin/dashboard.php'
        : '/cabinet/index.php'));
    exit;
}

/* ================= REGISTER ================= */
if ($action === 'register') {
    $phone = trim($_POST['phone'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $telegram = trim($_POST['telegram_username'] ?? '');
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$phone || !$fullname || !$gender || !$telegram || !$pass1 || !$pass2) {
        $error = 'Заполните все поля';
        return;
    }

    if ($pass1 !== $pass2) {
        $error = 'Пароли не совпадают';
        return;
    }

    if (strlen($pass1) < 6) {
        $error = 'Пароль минимум 6 символов';
        return;
    }

    $st = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $st->execute([$phone]);
    if ($st->fetch()) {
        $error = 'Этот номер уже зарегистрирован';
        return;
    }

    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    $st = $pdo->prepare("
        INSERT INTO users 
        (phone, fullname, gender, telegram_username, password_hash, role, status, theme)
        VALUES (?, ?, ?, ?, ?, 'employee', 'pending', 'light')
    ");
    $st->execute([$phone, $fullname, $gender, $telegram, $hash]);

    $success = 'Регистрация успешна. Ожидайте подтверждения администратора.';
}