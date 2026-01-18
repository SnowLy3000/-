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
 * Выйти из системы с записью лога
 */
function logout(): void
{
    // Подключаем БД прямо здесь для записи лога перед выходом
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/db.php';
    }

    $userId = $_SESSION['user']['id'] ?? 0;

    if ($userId) {
        /* === ЗАПИСЬ ВЫХОДА В ЛОГИ === */
        $stmtLog = $pdo->prepare("INSERT INTO user_sessions_log (user_id, action_type, ip_address) VALUES (?, 'logout', ?)");
        $stmtLog->execute([$userId, $_SERVER['REMOTE_ADDR']]);
        /* ============================ */
    }

    session_destroy();
    header('Location: /public/login.php');
    exit;
}
