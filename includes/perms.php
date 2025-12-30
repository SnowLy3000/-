<?php
// includes/perms.php
require_once __DIR__ . '/auth.php';

/**
 * ПРОВЕРКА ПРАВА (slug)
 * Проверяет, разрешена ли конкретная функция (например, 'edit_prices') текущему пользователю
 */
function can_user(string $permission_slug): bool {
    global $pdo;
    
    if (!isset($_SESSION['user']['id'])) return false;
    
    // Владелец (Owner) всегда имеет доступ ко всему без проверок
    if (has_role('Owner')) return true;

    // Убедимся, что подключение к БД активно
    if (!$pdo) { 
        require_once __DIR__ . '/db.php'; 
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ? AND p.slug = ?
        ");
        $stmt->execute([$_SESSION['user']['id'], $permission_slug]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * ПРОВЕРКА РОЛИ
 * Проверяет наличие системной роли (Owner, Admin и т.д.)
 */
function has_role(string $role): bool {
    global $pdo;
    
    if (!isset($_SESSION['user']['id'])) return false;
    
    if (!$pdo) { 
        require_once __DIR__ . '/db.php'; 
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name = ?
        ");
        $stmt->execute([$_SESSION['user']['id'], $role]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * ЖЕСТКИЙ ПРЕРЫВАТЕЛЬ (ОХРАННИК)
 * Если у пользователя нет ни нужной роли, ни нужного права — скрипт останавливается.
 */
function require_role(string $role_or_slug): void {
    // Если это не владелец, и у него нет такой роли, и нет такого права — доступ закрыт
    if (!has_role('Owner') && !has_role($role_or_slug) && !can_user($role_or_slug)) {
        http_response_code(403);
        exit("<div style='background:#0b1120; color:#ff6b6b; padding:40px; text-align:center; font-family:sans-serif; min-height:100vh;'>
                <div style='background:rgba(255,255,255,0.05); padding:30px; border-radius:20px; display:inline-block; border:1px solid rgba(255,107,107,0.2);'>
                    <h1 style='margin-top:0;'>⛔ Доступ ограничен</h1>
                    <p style='color:rgba(255,255,255,0.6);'>Ваша текущая роль не позволяет просматривать этот раздел или выполнять данное действие.</p>
                    <p style='font-size:13px; color:#785aff;'>Требуется право: <b>" . htmlspecialchars($role_or_slug) . "</b></p>
                    <br>
                    <a href='javascript:history.back()' style='background:#785aff; color:#fff; padding:12px 25px; border-radius:12px; text-decoration:none; font-weight:bold;'>← Вернуться назад</a>
                </div>
              </div>");
    }
}
