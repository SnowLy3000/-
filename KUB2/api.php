<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';
require_once 'db.php';
require_once 'auth.php'; 

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function send_success($data = [], $message = 'Success') {
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Метод не поддерживается.', 405);
}

$input = file_get_contents('php://input');
if ($input === false) {
    send_error('Не удалось получить входные данные.');
}

$data = json_decode($input, true);
$action = $data['action'] ?? $_GET['action'] ?? null;

// =================================================================
// 1. ПРОВЕРКА ПРАВ АДМИНИСТРАТОРА (ИСКЛЮЧЕНЫ load_settings, load_subtopic и т.д.)
// =================================================================

$admin_actions = [
    'add_group', 'add_subtopic', 'save_subtopic', 'delete_instruction_item', 
    'add_admin', 'delete_admin', 'load_admins', 
    'load_employees', 
    'save_settings', 'load_admin_questions', 'load_quiz_results',
    'load_branches', 'add_branch' 
];

if (in_array($action, $admin_actions)) {
    if (!isset($_SESSION['role']) || ($_SESSION['role'] !== ROLE_ADMIN && $_SESSION['role'] !== ROLE_MAIN_ADMIN)) {
        send_error('Доступ запрещен. Требуются права администратора.', 403);
    }
}

$auth_actions = ['check_in', 'save_quiz_result'];
if (in_array($action, $auth_actions)) {
    if (!isset($_SESSION['role'])) {
        send_error('Для этого действия требуется авторизация.', 403);
    }
}

// -------------------------------------------------------------------------
// А. ФУНКЦИИ БЕЗ ПРОВЕРКИ ПРАВ (доступны всем, включая гостей)
// -------------------------------------------------------------------------

if ($action === 'load_settings') {
    $sql = "SELECT setting_key, setting_value FROM settings";
    $result = db_query($sql);
    $settings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    send_success(['settings' => $settings]);
}

if ($action === 'get_instructions_data') {
    $data = [
        'instructions' => [],
        'quizData' => [
            'questions' => []
        ]
    ];
    
    $groups_sql = "SELECT id, title, icon FROM instruction_groups ORDER BY title ASC";
    $groups_result = db_query($groups_sql);
    
    if ($groups_result) {
        while ($group_row = $groups_result->fetch_assoc()) {
            $group_id = $group_row['id'];
            $group = [
                'id' => $group_id,
                'title' => $group_row['title'],
                'icon' => $group_row['icon'] ?? '',
                'subtopics' => []
            ];
            
            $subtopics_sql = "SELECT id, title, instruction, images FROM instruction_subtopics WHERE group_id = ? ORDER BY title ASC";
            $subtopics_result = db_query($subtopics_sql, [$group_id], 'i');
            
            if ($subtopics_result) {
                while ($subtopic_row = $subtopics_result->fetch_assoc()) {
                    $subtopic_row['images'] = json_decode($subtopic_row['images'] ?? '[]', true);
                    $group['subtopics'][] = $subtopic_row;
                }
            }
            $data['instructions'][] = $group;
        }
    }
    
    $questions_sql = "SELECT id, title, type, answers, correct_index, link_hint, category_title FROM quiz_questions";
    $questions_result = db_query($questions_sql);
    
    if ($questions_result) {
        while ($question_row = $questions_result->fetch_assoc()) {
            $question_row['answers'] = json_decode($question_row['answers'] ?? '[]', true);
            $question_row['correctIndex'] = $question_row['correct_index'];
            $question_row['category'] = $question_row['category_title'];
            unset($question_row['correct_index'], $question_row['category_title']);
            
            $data['quizData']['questions'][] = $question_row;
        }
    }
    
    send_success($data);
}

if ($action === 'load_subtopic') {
    $subtopic_id = (int)($data['subtopic_id'] ?? 0);
    if (!$subtopic_id) {
        send_error('Не указан ID подтемы.');
    }
    $sql = "SELECT id, group_id, title, instruction, images FROM instruction_subtopics WHERE id = ?";
    $result = db_query($sql, [$subtopic_id], 'i');
    
    if ($result && $result->num_rows === 1) {
        $subtopic = $result->fetch_assoc();
        $subtopic['images'] = json_decode($subtopic['images'] ?? '[]', true);
        send_success(['subtopic' => $subtopic]);
    } else {
        send_error('Подтема не найдена.', 404);
    }
}

if ($action === 'set_theme') {
    $theme_class = trim($data['theme_class'] ?? '');
    
    if (empty($theme_class)) {
        send_error('Не указана тема.');
    }
    
    $_SESSION['theme'] = $theme_class;
    setcookie('current_theme', $theme_class, time() + (86400 * 30), '/');
    
    if (isset($_SESSION['role']) && $_SESSION['role'] === ROLE_USER) {
        $user_id = $_SESSION['user_id'];
        $sql = "UPDATE users SET theme = ? WHERE id = ?";
        db_query($sql, [$theme_class, $user_id], 'si');
    }
    
    send_success([], 'Тема обновлена.');
}

// -------------------------------------------------------------------------
// B. ФУНКЦИИ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ (Сотрудники и Админы)
// -------------------------------------------------------------------------

if ($action === 'save_quiz_result') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] === ROLE_GUEST) {
        send_error('Для сохранения результатов требуется авторизация.', 403);
    }
    
    $user_id = $_SESSION['user_id'];
    $score = (int)($data['score'] ?? 0);
    $total_questions = (int)($data['total_questions'] ?? 0);
    $quiz_type = $data['quiz_type'] ?? 'test';
    
    $sql = "INSERT INTO quiz_results (user_id, score, total_questions, quiz_type) VALUES (?, ?, ?, ?)";
    $affected = db_query($sql, [$user_id, $score, $total_questions, $quiz_type], 'iiis');
    
    if ($affected > 0) {
        send_success(['id' => db_connect()->insert_id], 'Результат сохранен.');
    } else {
        send_error('Ошибка сохранения результата.');
    }
}

if ($action === 'check_in') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== ROLE_USER) {
        send_error('Для отметки необходимо войти как сотрудник.', 403);
    }
    
    $user_id = $_SESSION['user_id'];
    $branch_id = (int)($data['branch_id'] ?? 0);
    $today_date = date('Y-m-d');
    $check_in_time = date('H:i:s');
    
    if (!$branch_id) {
        send_error('Необходимо выбрать филиал.');
    }
    
    $check_sql = "SELECT id FROM attendance WHERE user_id = ? AND check_in_date = ?";
    $result = db_query($check_sql, [$user_id, $today_date], 'is');
    
    if ($result && $result->num_rows > 0) {
        send_error('Вы уже отмечались сегодня.', 409);
    }
    
    $sql = "INSERT INTO attendance (user_id, branch_id, check_in_date, check_in_time) VALUES (?, ?, ?, ?)";
    $affected = db_query($sql, [$user_id, $branch_id, $today_date, $check_in_time], 'iiss');
    
    if ($affected > 0) {
        send_success(['time' => $check_in_time], 'Отметка успешно поставлена!');
    } else {
        send_error('Ошибка сохранения отметки.', 500);
    }
}


// -------------------------------------------------------------------------
// C. ФУНКЦИИ АДМИНИСТРАТОРА
// -------------------------------------------------------------------------

if ($action === 'load_employees') {
    $sql = "SELECT id, username, phone, date_of_birth, created_at FROM users WHERE role = ?";
    $result = db_query($sql, [ROLE_USER], 's');
    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    send_success(['employees' => $employees]);
}

if ($action === 'add_group') {
    $title = trim($data['title'] ?? '');
    $icon = trim($data['icon'] ?? '');

    if (empty($title)) {
        send_error('Название группы не может быть пустым.');
    }
    
    $conn = db_connect();
    $sql = "INSERT INTO instruction_groups (title, icon) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
         $conn->close();
         send_error('Ошибка подготовки SQL: ' . $conn->error);
    }
    
    $stmt->bind_param('ss', $title, $icon);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $conn->close();
        send_success(['id' => $new_id], 'Группа успешно добавлена.'); 
    } else {
        $conn->close();
        send_error('Ошибка выполнения SQL: ' . $stmt->error); 
    }
}

if ($action === 'add_subtopic') {
    $group_id = (int)($data['group_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $instruction = $data['instruction'] ?? '';
    $images = json_encode($data['images'] ?? []); 

    if (!$group_id || empty($title)) {
        send_error('Необходимо указать группу и название подтемы.');
    }
    
    $conn = db_connect();
    $sql = "INSERT INTO instruction_subtopics (group_id, title, instruction, images) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isss', $group_id, $title, $instruction, $images);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $conn->close();
        send_success(['id' => $new_id], 'Подтема успешно добавлена.');
    } else {
        $conn->close();
        send_error('Ошибка добавления подтемы: ' . $stmt->error);
    }
}

if ($action === 'save_subtopic') {
    $id = (int)($data['id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $instruction_html = $data['instruction_html'] ?? '';
    $images_json = trim($data['images'] ?? '[]');

    if (!$id || empty($title)) {
        send_error('Не указан ID подтемы или заголовок.');
    }
    
    if (json_decode($images_json) === null && $images_json !== '[]') {
        send_error('Неверный формат JSON для изображений.');
    }

    $sql = "UPDATE instruction_subtopics SET title = ?, instruction = ?, images = ? WHERE id = ?";
    $affected = db_query($sql, [$title, $instruction_html, $images_json, $id], 'sssi');
    
    if ($affected >= 0) { 
        send_success(['id' => $id], 'Инструкция успешно сохранена.');
    } else {
        send_error('Ошибка сохранения инструкции.');
    }
}

if ($action === 'delete_instruction_item') {
    $type = $data['type'] ?? ''; 
    $id = (int)($data['id'] ?? 0);
    
    if (!$id || !in_array($type, ['group', 'subtopic'])) {
        send_error('Неверный тип или ID для удаления.');
    }
    
    if ($type === 'group') {
        db_query("DELETE FROM instruction_subtopics WHERE group_id = ?", [$id], 'i');
        $sql = "DELETE FROM instruction_groups WHERE id = ?";
    } else {
        $sql = "DELETE FROM instruction_subtopics WHERE id = ?";
    }
    
    $affected = db_query($sql, [$id], 'i');
    
    if ($affected > 0) {
        send_success([], 'Элемент успешно удален.');
    } else {
        send_error('Ошибка удаления. Элемент не найден или внутренняя ошибка.');
    }
}

if ($action === 'load_admin_questions') {
    $questions_sql = "SELECT id, title, type, correct_index, category_title, admin_hint FROM quiz_questions ORDER BY category_title, title ASC";
    $questions_result = db_query($questions_sql);
    
    $grouped_questions = [];
    if ($questions_result) {
        while ($q = $questions_result->fetch_assoc()) {
            $category = $q['category_title'] ?: 'Без категории';
            if (!isset($grouped_questions[$category])) {
                $grouped_questions[$category] = [];
            }
            $grouped_questions[$category][] = $q;
        }
    }
    
    send_success(['questions_by_category' => $grouped_questions]);
}

if ($action === 'load_admins') {
    $sql = "SELECT id, login, role, created_at FROM admins ORDER BY created_at ASC";
    $result = db_query($sql);
    $admins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    }
    send_success(['admins' => $admins]);
}

if ($action === 'add_admin') {
    $login = trim($data['login'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        send_error('Логин и пароль обязательны.');
    }
    
    $hashed_password = hash_password($password); 
    
    $sql = "INSERT INTO admins (login, password_hash, role) VALUES (?, ?, ?)";
    $affected = db_query($sql, [$login, $hashed_password, ROLE_ADMIN], 'sss');
    
    if ($affected > 0) {
        send_success([], 'Администратор успешно добавлен.');
    } else {
        send_error('Ошибка добавления администратора.');
    }
}

if ($action === 'delete_admin') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        send_error('Не указан ID администратора.');
    }
    
    $check_sql = "SELECT role FROM admins WHERE id = ?";
    $result = db_query($check_sql, [$id], 'i');
    if ($result && $result->num_rows > 0 && $result->fetch_assoc()['role'] === ROLE_MAIN_ADMIN) {
        send_error('Нельзя удалить главного администратора.');
    }
    
    $sql = "DELETE FROM admins WHERE id = ?";
    $affected = db_query($sql, [$id], 'i');
    
    if ($affected > 0) {
        send_success([], 'Администратор успешно удален.');
    } else {
        send_error('Ошибка удаления администратора.');
    }
}


if ($action === 'load_quiz_results') {
    $sql = "
        SELECT 
            qr.id,
            u.username,
            qr.score,
            qr.total_questions,
            qr.quiz_type,
            qr.created_at
        FROM quiz_results qr
        JOIN users u ON qr.user_id = u.id
        ORDER BY qr.created_at DESC
        LIMIT 50 
    ";
    
    $results = [];
    $query_result = db_query($sql);

    if ($query_result) {
        while ($row = $query_result->fetch_assoc()) {
            $row['percentage'] = $row['total_questions'] > 0 ? round(($row['score'] / $row['total_questions']) * 100) : 0;
            $results[] = $row;
        }
    }
    send_success(['results' => $results]);
}

if ($action === 'save_settings') {
     if ($_SESSION['role'] !== ROLE_MAIN_ADMIN) {
        send_error('Только Главный Администратор может менять настройки.', 403);
    }
    $quiz_count = (int)($data['quiz_questions_count'] ?? 0);
    $exam_timer = (int)($data['exam_timer_minutes'] ?? 0);

    if ($quiz_count < 5 || $exam_timer < 10) {
        send_error('Некорректные значения (минимум 5 вопросов, 10 минут таймер).');
    }
    
    $sql_quiz = "INSERT INTO settings (setting_key, setting_value) VALUES ('quiz_questions_count', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    db_query($sql_quiz, [$quiz_count], 'i');
    
    $sql_timer = "INSERT INTO settings (setting_key, setting_value) VALUES ('exam_timer_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    db_query($sql_timer, [$exam_timer], 'i');
    
    send_success([], 'Настройки успешно сохранены.');
}

if ($action === 'add_branch') {
    $name = trim($data['name'] ?? '');

    if (empty($name)) {
        send_error('Название филиала не может быть пустым.');
    }
    
    $check_sql = "SELECT id FROM branches WHERE name = ?";
    if (db_query($check_sql, [$name], 's')->num_rows > 0) {
        send_error('Филиал с таким названием уже существует.');
    }

    $sql = "INSERT INTO branches (name) VALUES (?)";
    $affected = db_query($sql, [$name], 's');
    
    if ($affected > 0) {
        $id_result = db_query("SELECT LAST_INSERT_ID() as id");
        $new_id = $id_result->fetch_assoc()['id'];
        send_success(['id' => $new_id, 'name' => $name], 'Филиал успешно добавлен.');
    } else {
        send_error('Ошибка добавления филиала.');
    }
}

if ($action === 'load_branches') {
    $sql = "SELECT id, name FROM branches ORDER BY name ASC";
    $result = db_query($sql);
    $branches = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $branches[] = $row;
        }
    }
    send_success(['branches' => $branches]);
}

if ($action === 'load_attendance') {
    $date = $data['date'] ?? date('Y-m-d');
    $branch_id = (int)($data['branch_id'] ?? 0);
    
    $sql = "
        SELECT 
            u.username, 
            u.phone, 
            b.name as branch_name, 
            a.check_in_time
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN branches b ON a.branch_id = b.id
        WHERE a.check_in_date = ?
    ";
    $params = [$date];
    $types = 's';
    
    if ($branch_id > 0) {
        $sql .= " AND a.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY b.name, a.check_in_time ASC";
    
    $result = db_query($sql, $params, $types);
    $attendance = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['status'] = 'Отметился'; 
            $attendance[] = $row;
        }
    }
    send_success(['attendance' => $attendance, 'date' => $date]);
}

send_error('Неизвестное действие.', 404);