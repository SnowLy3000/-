<?php
// /admin/ajax/import_csv.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

header('Content-Type: application/json');

// Проверка прав доступа
if (!is_logged_in() || !has_role('Admin') && !has_role('Owner')) {
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['status' => 'error', 'message' => 'Файл не получен']);
    exit;
}

$file = $_FILES['file']['tmp_name'];
$handle = fopen($file, "r");

// Пропускаем BOM если он есть
if (fgets($handle, 4) !== "\xEF\xBB\xBF") rewind($handle);

$updated = 0;
$created = 0;
$errors = 0;
$row_count = 0;

// Начинаем транзакцию для безопасности
$pdo->beginTransaction();

try {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row_count++;
        if ($row_count === 1) continue; // Пропускаем заголовок (Наименование, Цена)

        $name = trim($data[0] ?? '');
        $price = (float)str_replace(',', '.', $data[1] ?? 0);

        if (empty($name)) continue;

        // Ищем товар по имени
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $product = $stmt->fetch();

        if ($product) {
            // ОБНОВЛЯЕМ ЦЕНУ
            $update = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
            $update->execute([$price, $product['id']]);
            $updated++;
        } else {
            // СОЗДАЕМ НОВЫЙ ТОВАР (category_id = 1 по умолчанию)
            $insert = $pdo->prepare("INSERT INTO products (name, price, category_id, is_active) VALUES (?, ?, 1, 1)");
            $insert->execute([$name, $price]);
            $created++;
        }
    }
    
    $pdo->commit();
    fclose($handle);

    echo json_encode([
        'status' => 'success',
        'message' => "Обработано строк: $row_count. <br>Обновлено товаров: $updated. <br>Создано новых: $created."
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
