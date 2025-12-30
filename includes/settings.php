<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

function setting(string $key, $default = null) {
    static $cache = null;
    global $pdo;

    if ($cache === null) {
        $cache = [];
        $stmt = $pdo->query("SELECT skey, svalue FROM settings");
        foreach ($stmt as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }

    return $cache[$key] ?? $default;
}