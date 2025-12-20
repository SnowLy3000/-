<?php
$current = $_GET['page'] ?? 'dashboard';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Cabinet — <?= ucfirst($current) ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>

<div class="cabinet-wrap">
    <aside class="cabinet-menu neon">
        <div class="menu-title">👤 Кабинет</div>

        <a class="<?= $current==='dashboard'?'active':'' ?>" href="/cabinet/index.php?page=dashboard">
            🏠 Dashboard
        </a>

        <a class="<?= $current==='shifts'?'active':'' ?>" href="/cabinet/index.php?page=shifts">
            📅 Shifts
        </a>

        <a class="<?= $current==='late'?'active':'' ?>" href="/cabinet/index.php?page=late">
            ⏰ Late
        </a>

        <a class="<?= $current==='profile'?'active':'' ?>" href="/cabinet/index.php?page=profile">
            👤 Profile
        </a>
        
        <a class="<?= $current==='checkin'?'active':'' ?>" href="/cabinet/index.php?page=checkin">
    🟢 Отметиться
</a>

        <hr>

        <a href="/logout.php">🚪 Выйти</a>
    </aside>

    <main class="cabinet-main">