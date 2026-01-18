<?php
// /cabinet/index.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/perms.php';
require_once __DIR__ . '/../includes/router.php';

require_auth();

if (!has_role('Employee') && !has_role('Admin') && !has_role('Owner') && !has_role('Marketing')) {
    http_response_code(403);
    exit('Access denied');
}

$page = q('page', 'dashboard');
$pages = cabinet_pages();

if (!isset($pages[$page])) {
    $page = 'dashboard';
}

$area = 'cabinet';

// ПРОВЕРКА: Если это AJAX запрос, не подключаем header и footer
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$isAjax) {
    require __DIR__ . '/../includes/layout/header.php';
}

require $pages[$page];

if (!$isAjax) {
    require __DIR__ . '/../includes/layout/footer.php';
}