<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/auth_logic.php';

// Если уже залогинен — отправляем куда нужно
if (is_logged_in()) {
    if (in_array($_SESSION['user']['role'] ?? '', ['owner','admin'], true)) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /cabinet/index.php');
    }
    exit;
}

$title = 'Вход в KUB';

// подключаем стили/скрипты страницы (layout их подхватит)
$page_css = '/assets/css/auth.css';
$page_js  = '/assets/js/auth-ui.js';

require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/auth_layout.php';
require __DIR__ . '/../includes/layout/footer.php';