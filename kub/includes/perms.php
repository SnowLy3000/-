<?php

require __DIR__ . '/db.php';

/**
 * Загружаем права пользователя
 */
function load_permissions_for_user(int $userId): array {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT p.code
        FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$userId]);

    return array_column($stmt->fetchAll(), 'code');
}

/**
 * Проверка права
 */
function user_has(string $permission): bool {
    $role = $_SESSION['user']['role'] ?? null;

    // Owner имеет все права
    if ($role === 'owner') {
        return true;
    }

    $perms = $_SESSION['user']['permissions'] ?? [];
    return in_array($permission, $perms, true);
}

/**
 * Требовать конкретное право
 */
function require_permission(string $permission): void {
    if (!user_has($permission)) {
        http_response_code(403);
        exit('Permission denied: ' . htmlspecialchars($permission));
    }
}
