<?php
// /includes/auth_logic.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Регистрация сотрудника (status = pending)
 */
function register_user(array $data): array
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO users 
        (first_name, last_name, phone, telegram, gender, password_hash, status)
        VALUES (:first_name, :last_name, :phone, :telegram, :gender, :password, 'pending')
    ");

    $stmt->execute([
        ':first_name' => trim($data['first_name']),
        ':last_name'  => trim($data['last_name']),
        ':phone'      => trim($data['phone']),
        ':telegram'   => trim($data['telegram'] ?? ''),
        ':gender'     => $data['gender'] ?? 'other',
        ':password'   => password_hash($data['password'], PASSWORD_DEFAULT),
    ]);

    return ['success' => true];
}

/**
 * Вход пользователя
 * Admin/Owner/Marketing → username
 * Employee             → phone
 */
function login_user(string $login, string $password): bool
{
    global $pdo;

    // ⚠️ ВАЖНО: два разных параметра
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE username = :username
           OR phone = :phone
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $login,
        ':phone'    => $login,
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if ($user['status'] !== 'active') {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Получаем роли пользователя
    $rolesStmt = $pdo->prepare("
        SELECT r.name
        FROM roles r
        JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = :uid
    ");

    $rolesStmt->execute([
        ':uid' => $user['id'],
    ]);

    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

    $_SESSION['user'] = [
        'id'         => $user['id'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'roles'      => $roles,
    ];

    return true;
}
