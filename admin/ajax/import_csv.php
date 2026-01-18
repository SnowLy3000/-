<?php
/**
 * admin/ajax/import_csv.php
 */

ob_start(); // Перехват случайного вывода

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

if (ob_get_length()) ob_clean(); // Стираем мусор
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role('Admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $content = file_get_contents($file);
    
    // Автоопределение и исправление кодировки
    $enc = mb_detect_encoding($content, ['UTF-8', 'Windows-1251']);
    if ($enc !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $enc);
    
    // Определение разделителя
    $lines = explode("\n", $content);
    $separator = (strpos($lines[0] ?? '', ';') !== false) ? ";" : ",";

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    
    $updated = 0; $created = 0; $row_count = 0;
    $pdo->beginTransaction();

    try {
        while (($data = fgetcsv($stream, 1000, $separator)) !== FALSE) {
            $row_count++;
            if ($row_count === 1) continue; // Пропуск заголовка

            $name = trim($data[0] ?? '');
            $priceRaw = str_replace([' ', ','], ['', '.'], $data[1] ?? '0');
            $price = (float)$priceRaw;

            if (empty($name)) continue;

            // Поиск дубликата (без учета регистра)
            $stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmt->execute([$name]);
            $product = $stmt->fetch();

            if ($product) {
                $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$price, $product['id']]);
                $updated++;
            } else {
                $pdo->prepare("INSERT INTO products (name, price, category_id, is_active) VALUES (?, ?, 1, 1)")->execute([$name, $price]);
                $created++;
            }
        }
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Обработано строк: $row_count. Обновлено: $updated. Создано: $created."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    fclose($stream);
    exit;
}
