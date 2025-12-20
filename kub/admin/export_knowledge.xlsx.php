<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

require __DIR__ . '/../libs/PhpSpreadsheet/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Темы
$themes = $pdo->query("
    SELECT id, title FROM themes ORDER BY title
")->fetchAll();

// Сотрудники
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role='employee' AND status='active'
    ORDER BY fullname
")->fetchAll();

// Прочитано
$views = $pdo->query("
    SELECT user_id, theme_id
    FROM knowledge_views
    WHERE theme_id IS NOT NULL
")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN);

// Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('База знаний');

// Заголовок
$header = ['Сотрудник'];
foreach ($themes as $t) {
    $header[] = $t['title'];
}
$sheet->fromArray([$header], null, 'A1');

// Данные
$row = 2;
foreach ($users as $u) {
    $line = [$u['fullname']];

    foreach ($themes as $t) {
        $read = isset($views[$u['id']]) && in_array($t['id'], $views[$u['id']]);
        $line[] = $read ? 'Да' : 'Нет';
    }

    $sheet->fromArray([$line], null, 'A' . $row);
    $row++;
}

// Автоширина
foreach (range('A', chr(64 + count($header))) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Вывод
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="knowledge_reading.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;