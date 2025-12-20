<?php
/**
 * logout.php
 * Универсальный выход из системы
 */

// подключаем config ТАК ЖЕ, как в auth.php
$config = require __DIR__ . '/includes/config.php';

// обязательно то же имя сессии
session_name($config['app']['session_name']);
session_start();

// чистим данные
$_SESSION = [];

// удаляем cookie сессии
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// уничтожаем сессию
session_destroy();

// редирект на логин
header('Location: /public/index.php');
exit;