<?php

// config всегда рядом
$config = require __DIR__ . '/config.php';

// используем одно и то же имя сессии во всём проекте
session_name($config['app']['session_name']);
session_start();

/**
 * Проверка, залогинен ли пользователь
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

/**
 * Требовать авторизацию
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /public/index.php');
        exit;
    }
}

/**
 * Требовать админа или владельца
 */
function require_admin(): void {
    require_login();

    $role = $_SESSION['user']['role'] ?? null;

    if (!in_array($role, ['owner', 'admin'], true)) {
        http_response_code(403);
        exit('Access denied. Admin only.');
    }
}