<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (($_GET['api'] ?? '') !== '') {
    api_controller_dispatch();
    exit;
}

$page = (string) ($_GET['page'] ?? '');
if ($page === '') {
    auth_controller_dispatch();
    exit;
}

if (!page_controller_dispatch($page)) {
    auth_controller_dispatch();
}
