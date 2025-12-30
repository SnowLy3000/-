<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/perms.php';

// Если пользователь уже вошел в систему
if (is_logged_in()) {
    // Отправляем админов в админку, остальных в кабинет
    if (has_role('Admin') || has_role('Owner') || has_role('Marketing')) {
        header('Location: /admin/index.php?page=dashboard');
    } else {
        header('Location: /cabinet/index.php?page=dashboard');
    }
    exit;
} else {
    // Если не вошел — отправляем на страницу логина
    header('Location: login.php');
    exit;
}
