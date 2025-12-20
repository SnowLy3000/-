<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($title ?? 'KUB') ?></title>

<!-- AUTH DESIGN -->
<link rel="stylesheet" href="/assets/css/auth.css">

</head>
<body data-theme="<?= htmlspecialchars($_SESSION['user']['theme'] ?? 'light') ?>">