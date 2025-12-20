<?php
require __DIR__ . '/../includes/auth.php';
require_login();

$page = $_GET['page'] ?? 'dashboard';

// разрешённые страницы
$allowedPages = ['dashboard','profile','shifts','late'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

// layout
require __DIR__ . '/../includes/layout/cabinet_header.php';

// контент
require __DIR__ . '/pages/' . $page . '.php';

// footer
require __DIR__ . '/../includes/layout/cabinet_footer.php';