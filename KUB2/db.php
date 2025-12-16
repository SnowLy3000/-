<?php
require_once 'config.php';

function db_connect() {
    // Включаем отчет об ошибках SQL/PHP
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        // Установка кодировки для корректной работы с русским языком и эмодзи
        $conn->set_charset("utf8mb4"); 
        return $conn;
    } catch (Exception $e) {
        // Если соединение не удалось, выводим ошибку в JSON для AJAX
        if (strpos($_SERVER['REQUEST_URI'], 'api.php') !== false) {
             header('Content-Type: application/json');
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных: ' . $e->getMessage()]);
             exit;
        }
        // Для обычных страниц
        die("Connection failed: " . $e->getMessage());
    }
}

// Универсальная функция для выполнения запросов
function db_query($sql, $params = [], $types = '') {
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $error = "SQL Prepare Error: " . $conn->error . " | SQL: " . $sql;
        $conn->close();
        if (strpos($_SERVER['REQUEST_URI'], 'api.php') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $error]);
            exit;
        }
        die($error);
    }

    if ($params && $types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();

    if (!$success) {
        $error = "SQL Execute Error: " . $stmt->error . " | SQL: " . $sql;
        $stmt->close();
        $conn->close();
        if (strpos($_SERVER['REQUEST_URI'], 'api.php') !== false) {
             header('Content-Type: application/json');
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'SQL Execute Error: ' . $error]);
             exit;
        }
        die($error);
    }
    
    if (strpos(trim(strtoupper($sql)), 'SELECT') === 0) {
        $result = $stmt->get_result();
        $stmt->close();
        $conn->close();
        return $result;
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    return $affected_rows;
}