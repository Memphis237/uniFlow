<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

function service_catalog_category_slug(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
    $normalized = mb_strtolower($ascii !== false ? $ascii : $value);

    return match (true) {
        str_contains($normalized, 'coiff') => 'coiffure',
        str_contains($normalized, 'cours'),
        str_contains($normalized, 'soutien') => 'cours',
        str_contains($normalized, 'demenag') => 'demenagement',
        str_contains($normalized, 'informat') => 'informatique',
        str_contains($normalized, 'photo'),
        str_contains($normalized, 'crea') => 'photo',
        default => 'autres',
    };
}

function service_catalog_search(
    PDO $pdo,
    int $viewerId,
    array $input,
    bool $searchEnabled = true,
    int $pageSize = 24,
    bool $favoritesOnly = false
): array {
    require_once __DIR__ . '/../Models/serviceCatalogModel.php';

    return service_catalog_model_search(
        $pdo,
        $viewerId,
        $input,
        $searchEnabled,
        $pageSize,
        $favoritesOnly
    );
}
