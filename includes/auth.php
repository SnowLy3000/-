<?php
// /includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверка: пользователь авторизован?
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

/**
 * Получить текущего пользователя
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Требовать авторизацию
 */
function require_auth(): void
{
    if (!is_logged_in()) {
        header('Location: /public/login.php');
        exit;
    }
}

/**
 * Выйти из системы
 */
function logout(): void
{
    session_destroy();
    header('Location: /public/login.php');
    exit;
}
