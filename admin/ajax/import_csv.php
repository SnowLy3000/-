<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

if (!has_role('Admin') && !has_role('Owner')) {
    echo json_encode(['status' => 'error', 'message' => 'Нет прав']);
    exit;
}

// Проверка и добавление колонки price (если вдруг её еще нет)
try {
    $pdo->query("SELECT price FROM products LIMIT 1");
} catch (Exception $e) {
    $pdo->query("ALTER TABLE products ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $content = file_get_contents($file);
    
    // Исправляем кодировку (Windows-1251 -> UTF-8)
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251']);
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    $separator = ",";
    $lines = explode("\n", $content);
    $checkLine = $lines[3] ?? $lines[2] ?? ''; 
    if (strpos($checkLine, ';') !== false) $separator = ";";

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    
    $imported = 0; $updated = 0; $skipped = 0;

    // Пропускаем шапку (3 строки)
    fgetcsv($stream, 2000, $separator); 
    fgetcsv($stream, 2000, $separator); 
    fgetcsv($stream, 2000, $separator);

    while (($data = fgetcsv($stream, 2000, $separator)) !== FALSE) {
        if (count($data) < 9) continue;

        $name    = trim($data[3] ?? ''); 
        $percent = (float)str_replace(',', '.', $data[5] ?? 0);
        $price   = (float)str_replace(',', '.', $data[8] ?? 0);

        // --- БЛОК ФИЛЬТРАЦИИ ---
        if (empty($name) || mb_strlen($name) < 3) continue;

        // Список стоп-слов (исправлено: закрыты кавычки)
        $stopWords = [
            'Moldcell Cartela', 
            'Orange Cartela', 
            'Unite Cartela', 
        ];

        $shouldSkip = false;
        foreach ($stopWords as $word) {
            if (mb_stripos($name, $word) !== false) {
                $shouldSkip = true;
                break;
            }
        }
        
        if ($shouldSkip || $price <= 0) continue;
        // --- КОНЕЦ ФИЛЬТРАЦИИ ---

        // Поиск категории
        $stmt = $pdo->prepare("SELECT id FROM salary_categories WHERE percent = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$percent]);
        $cat = $stmt->fetch();

        if (!$cat) { $skipped++; continue; }

        // Поиск и сохранение товара
        $stmt = $pdo->prepare("SELECT id, price FROM products WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($price > (float)$existing['price']) {
                $stmt = $pdo->prepare("UPDATE products SET price = ?, category_id = ? WHERE id = ?");
                $stmt->execute([$price, $cat['id'], $existing['id']]);
                $updated++;
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt->execute([$name, $cat['id'], $price]);
            $imported++;
        }
    }
    fclose($stream);

    echo json_encode([
        'status' => 'success',
        'message' => "<b>Готово!</b><br>Новых товаров: $imported<br>Цен уточнено: $updated<br>Пропущено (мусор/нет категорий): $skipped"
    ]);
    exit;
}
