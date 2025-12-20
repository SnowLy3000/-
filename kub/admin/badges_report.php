<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_admin();

$rows = $pdo->query("
    SELECT u.fullname, b.badge_code, b.awarded_at
    FROM user_badges b
    JOIN users u ON u.id=b.user_id
    ORDER BY b.awarded_at DESC
")->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Бейджи сотрудников</title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<h1>🥇 Бейджи сотрудников</h1>

<table border="1" cellpadding="8">
<tr>
  <th>Сотрудник</th>
  <th>Бейдж</th>
  <th>Дата</th>
</tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= htmlspecialchars($r['fullname']) ?></td>
  <td><?= htmlspecialchars($r['badge_code']) ?></td>
  <td><?= $r['awarded_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>