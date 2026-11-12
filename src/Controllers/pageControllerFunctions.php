<?php

declare(strict_types=1);

function page_controller_dispatch(string $page): bool
{
    if ($page === 'logout') {
        require_once __DIR__ . '/logoutControllerFunctions.php';
        logout_controller_dispatch();
        return true;
    }

    $pages = [
        'home' => 'home',
        'services' => 'services',
        'favorites' => 'favorites',
        'campus' => 'campus',
        'messages' => 'message',
        'profile' => 'profile',
        'admin' => 'admin',
        'help' => 'help',
    ];

    if (!isset($pages[$page])) {
        return false;
    }

    require dirname(__DIR__) . '/Views/pages/' . $pages[$page] . '.php';
    return true;
}
