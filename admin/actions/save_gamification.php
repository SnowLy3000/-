<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

// Инициализация пользователя
$user = current_user();

if (!$user || (!has_role('Admin') && !has_role('Owner'))) { 
    die("Доступ запрещен. Недостаточно прав."); 
}

// 1. УДАЛЕНИЕ ГРЕЙДА
if (isset($_GET['delete_grade'])) {
    $stmt = $pdo->prepare("DELETE FROM user_grades WHERE id = ?");
    $stmt->execute([$_GET['delete_grade']]);
    header("Location: /admin/index.php?page=gamification_hub");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 2. СОХРАНЕНИЕ ВЕСОВ XP
    if ($_POST['action'] === 'save_settings') {
        $allowed = ['xp_test_passed', 'xp_exam_passed', 'xp_instruction_read', 'xp_checkin_ontime', 'xp_perfect_week', 'xp_bug_bounty_base'];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $_POST[$key]]);
            }
        }
    }

    // 3. СОХРАНЕНИЕ / РЕДАКТИРОВАНИЕ УРОВНЕЙ
    if ($_POST['action'] === 'save_grade') {
        $id = $_POST['grade_id'];
        $title = $_POST['title'];
        $icon = $_POST['icon'];
        $min_xp = (int)$_POST['min_xp'];

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE user_grades SET title = ?, icon = ?, min_xp = ? WHERE id = ?");
            $stmt->execute([$title, $icon, $min_xp, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_grades (title, icon, min_xp) VALUES (?, ?, ?)");
            $stmt->execute([$title, $icon, $min_xp]);
        }
    }

    // 4. РУЧНОЕ НАЧИСЛЕНИЕ XP
    if ($_POST['action'] === 'give_manual_xp') {
        $target_user_id = (int)$_POST['user_id'];
        $amount = (int)$_POST['amount'];
        $reason = $_POST['reason'] ?: 'Бонус от администратора';
        
        // Берем ID текущего админа из сессии или объекта $user
        $admin_id = $user['id']; 

        $stmt = $pdo->prepare("INSERT INTO user_xp_log (user_id, admin_id, amount, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$target_user_id, $admin_id, $amount, $reason]);
        
        header("Location: /admin/index.php?page=users&success=xp");
        exit;
    }
}

header("Location: /admin/index.php?page=gamification_hub");
exit;