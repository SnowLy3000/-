<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('TEST_MANAGE');

// Заголовки CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_surveys.csv');
header('Pragma: public');
header('Cache-Control: max-age=0');

// Открываем поток
$out = fopen('php://output', 'w');

// BOM для Excel (чтобы кириллица не ломалась)
fputs($out, "\xEF\xBB\xBF");

// Активные анкеты
$totalSurveys = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM surveys 
    WHERE active = 1
")->fetchColumn();

// Заголовок
fputcsv($out, ['Сотрудник', 'Ответил', 'Всего анкет', 'Статус']);

// Сотрудники
$users = $pdo->query("
    SELECT id, fullname
    FROM users
    WHERE role = 'employee' 
      AND status = 'active'
    ORDER BY fullname
")->fetchAll();

// Ответы
$answers = $pdo->query("
    SELECT user_id, COUNT(DISTINCT survey_id) AS cnt
    FROM survey_answers
    GROUP BY user_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($users as $u) {
    $answered = (int)($answers[$u['id']] ?? 0);

    if ($totalSurveys === 0) {
        $status = '—';
    } elseif ($answered === 0) {
        $status = 'Проблема';
    } elseif ($answered < $totalSurveys) {
        $status = 'Частично';
    } else {
        $status = 'ОК';
    }

    fputcsv($out, [
        $u['fullname'],
        $answered,
        $totalSurveys,
        $status
    ]);
}

fclose($out);
exit;