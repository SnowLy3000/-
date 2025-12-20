<?php
require __DIR__ . '/../includes/db.php';

// сколько дней ждём
$days = 3;

// выбираем пользователей + инструкции,
// которые НЕ читались X дней
$stmt = $pdo->query("
    SELECT 
        u.id AS user_id,
        s.id AS subtheme_id,
        s.title
    FROM subthemes s
    CROSS JOIN users u
    LEFT JOIN knowledge_views kv
        ON kv.subtheme_id = s.id
       AND kv.user_id = u.id
    LEFT JOIN notifications n
        ON n.user_id = u.id
       AND n.related_id = s.id
       AND n.type = 'knowledge_reminder'
    WHERE kv.id IS NULL
      AND n.id IS NULL
      AND s.created_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY)
");

$rows = $stmt->fetchAll();

foreach ($rows as $r) {
    $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, related_id)
        VALUES (?, ?, ?, 'knowledge_reminder', ?)
    ")->execute([
        $r['user_id'],
        'Непрочитанная инструкция',
        'Вы не ознакомились с инструкцией: «'.$r['title'].'»',
        $r['subtheme_id']
    ]);
}