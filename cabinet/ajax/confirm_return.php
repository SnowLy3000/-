<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_auth();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id = (int)$_POST['sale_id'];
    $return_1c_id = htmlspecialchars($_POST['1c_return_id'] ?? '');
    $reason = htmlspecialchars($_POST['reason_type'] ?? '');
    $defect_desc = htmlspecialchars($_POST['defect_desc'] ?? '');
    
    $selected_item_ids = $_POST['return_items'] ?? []; 
    $return_qtys = $_POST['return_qty'] ?? []; 

    try {
        $pdo->beginTransaction();

        $deduct_total_money = 0;
        $deduct_total_salary = 0;
        $return_details = [];

        foreach ($selected_item_ids as $itemId) {
            $qty_to_return = (int)$return_qtys[$itemId];

            $st = $pdo->prepare("SELECT product_name, price, discount, salary_amount, quantity, brand FROM sale_items WHERE id = ?");
            $st->execute([$itemId]);
            $item = $st->fetch();

            if ($item) {
                $price_per_one = ceil($item['price'] - ($item['price'] * $item['discount'] / 100));
                $salary_per_one = $item['salary_amount'] / $item['quantity'];

                $deduct_total_money += ($price_per_one * $qty_to_return);
                $deduct_total_salary += ($salary_per_one * $qty_to_return);
                $return_details[] = $item['product_name'] . " (" . $qty_to_return . " шт.)";

                if ($qty_to_return >= $item['quantity']) {
                    // Если возвращаем всё количество — просто помечаем строку как возвращенную
                    $pdo->prepare("UPDATE sale_items SET is_returned = 1 WHERE id = ?")->execute([$itemId]);
                } else {
                    // Если возвращаем ЧАСТЬ (например, 1 из 2):
                    // 1. Уменьшаем количество в текущей активной строке
                    $new_qty = $item['quantity'] - $qty_to_return;
                    $new_salary = $item['salary_amount'] - ($salary_per_one * $qty_to_return);
                    $pdo->prepare("UPDATE sale_items SET quantity = ?, salary_amount = ? WHERE id = ?")
                        ->execute([$new_qty, $new_salary, $itemId]);
                    
                    // 2. Создаем новую строку специально для "истории возврата" этого товара
                    $stmtAdd = $pdo->prepare("INSERT INTO sale_items (sale_id, product_name, price, discount, quantity, salary_amount, is_returned, brand) 
                                              VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
                    $stmtAdd->execute([$sale_id, $item['product_name'], $item['price'], $item['discount'], $qty_to_return, ($salary_per_one * $qty_to_return), $item['brand']]);
                }
            }
        }

        // Обновляем общую сумму чека
        $pdo->prepare("UPDATE sales SET total_amount = total_amount - ? WHERE id = ?")
            ->execute([$deduct_total_money, $sale_id]);

        // Проверяем, остались ли в чеке НЕ возвращенные товары
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND is_returned = 0");
        $stmt_check->execute([$sale_id]);
        if ($stmt_check->fetchColumn() == 0) {
            $pdo->prepare("UPDATE sales SET is_returned = 1 WHERE id = ?")->execute([$sale_id]);
        }

        $items_log = "Возвращено: " . implode(", ", $return_details);
        $final_comment = $items_log . "\n" . $defect_desc;

        $stmt = $pdo->prepare("INSERT INTO returns (sale_id, return_1c_id, staff_id, reason, defect_description, status) VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$sale_id, $return_1c_id, $user['id'], $reason, $final_comment]);

        $pdo->commit();
        echo "<script>alert('Возврат оформлен. Списано: {$deduct_total_money} L'); window.location.href='../index.php?page=sales_history';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Ошибка: " . $e->getMessage());
    }
}
