<?php

declare(strict_types=1);

function render_view(string $template, array $data = []): void
{
    $path = dirname(__DIR__) . '/Views/' . ltrim($template, '/') . '.php';
    if (!is_file($path)) {
        throw new RuntimeException('Template introuvable : ' . $template);
    }

    extract($data, EXTR_SKIP);
    require $path;
}
