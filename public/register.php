<?php
require_once __DIR__ . '/../includes/auth_logic.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    register_user($_POST);
    $message = 'Регистрация отправлена. Ожидайте подтверждения администратора.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Регистрация — KUB</title>
</head>
<body>
<h2>Регистрация сотрудника</h2>

<?php if ($message): ?>
<p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post">
    <input name="first_name" placeholder="Имя" required><br>
    <input name="last_name" placeholder="Фамилия" required><br>
    <input name="phone" placeholder="Телефон (8 цифр)" required><br>
    <input name="telegram" placeholder="Telegram"><br>
    <select name="gender">
        <option value="male">Мужской</option>
        <option value="female">Женский</option>
        <option value="other">Другое</option>
    </select><br>
    <input type="password" name="password" placeholder="Пароль" required><br>
    <button type="submit">Зарегистрироваться</button>
</form>

<a href="login.php">Вход</a>
</body>
</html>
