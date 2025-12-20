<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

// Заголовки CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=knowledge_reading.csv');
header('Pragma: public');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');

// BOM
fputs($out, "\xEF\xBB\xBF");

// Темы
$themes = $pdo->query("
    SELECT id, title 
    FROM themes 
    ORDER BY title
")->fetchAll();

// Заголовок CSV
$header = ['Сотрудник'];
foreach ($themes as $t) {
    $header[] = $t['title'];
}
fputcsv($out, $header);

// Сотрудники
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'employee'
      AND status = 'active'
    ORDER BY fullname
")->fetchAll();

// Прочитано
$views = $pdo->query("
    SELECT user_id, theme_id
    FROM knowledge_views
    WHERE theme_id IS NOT NULL
")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

foreach ($users as $u) {
    $row = [$u['fullname']];

    foreach ($themes as $t) {
        $read = isset($views[$u['id']]) && in_array($t['id'], $views[$u['id']]);
        $row[] = $read ? 'Да' : 'Нет';
    }

    fputcsv($out, $row);
}

fclose($out);
exit;