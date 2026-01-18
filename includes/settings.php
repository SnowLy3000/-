<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

/**
 * Получение настройки
 */
function setting(string $key, $default = null) {
    static $cache = null;
    global $pdo;

    if ($cache === null) {
        $cache = [];
        try {
            $stmt = $pdo->query("SELECT skey, svalue FROM settings");
            foreach ($stmt as $row) {
                $cache[$row['skey']] = $row['svalue'];
            }
        } catch (PDOException $e) {
            // Если таблицы еще нет или ошибка БД
            return $default;
        }
    }

    return $cache[$key] ?? $default;
}

/**
 * Сохранение настройки
 */
function setting_set(string $key, $value) {
    global $pdo;
    
    try {
        // Преобразуем значение в строку, если это массив или объект
        $val = is_array($value) ? json_encode($value) : $value;

        $stmt = $pdo->prepare("
            INSERT INTO settings (skey, svalue) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)
        ");
        return $stmt->execute([$key, $val]);
    } catch (PDOException $e) {
        // Можно добавить логирование ошибки здесь
        return false;
    }
}
