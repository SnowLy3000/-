<?php
// /admin/index.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/perms.php';
require_once __DIR__ . '/../includes/router.php';

require_auth();

// Доступ в админку: Admin/Owner (Marketing тоже можно, если хочешь)
if (!has_role('Admin') && !has_role('Owner') && !has_role('Marketing')) {
    http_response_code(403);
    exit('Access denied');
}

$page = q('page', 'dashboard');
$pages = admin_pages();

if (!isset($pages[$page])) {
    $page = 'dashboard';
}

$area = 'admin';
require __DIR__ . '/../includes/layout/header.php';
require $pages[$page];
require __DIR__ . '/../includes/layout/footer.php';
