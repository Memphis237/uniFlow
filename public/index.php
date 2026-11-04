<?php
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/FavoritesController.php';

$action = $_GET['action'] ?? 'profile';

if ($action === 'profile') {
    (new UserController())->showProfile();
} elseif ($action === 'services') {
    (new FavoritesController())->services();
} elseif ($action === 'favorites') {
    (new FavoritesController())->favorites();
} elseif ($action === 'favorite') {
    (new FavoritesController())->mutate();
} elseif ($action === 'service-create') {
    (new FavoritesController())->createService();
} elseif ($action === 'service-delete') {
    (new FavoritesController())->deleteService();
} else {
    http_response_code(404);
    echo 'Page introuvable.';
}
