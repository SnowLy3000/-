<?php
// /public/logout.php
require_once __DIR__ . '/../includes/auth.php';

// Функция logout() в auth.php теперь сама запишет время выхода в базу
// и перенаправит пользователя на страницу логина.
logout();
