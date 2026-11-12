<?php

declare(strict_types=1);

function service_controller_handle_api(
    string $api,
    array $actor,
    PDO $database
): bool {
    $serviceId = (int) ($_GET['id'] ?? $_POST['service_id'] ?? 0);
    $userId = (int) $actor['Id_users'];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($api === 'service-create' && $method === 'POST') {
        $data = service_controller_validate_create($_POST, $_FILES['images'] ?? null);
        service_model_create($database, $actor, $data);
        api_controller_respond(['ok' => true], 200);
    }

    if ($api === 'service-like' && $method === 'POST') {
        $result = service_model_toggle_favorite($database, $serviceId, $userId);
        api_controller_respond($result, 200);
    }

    if ($api === 'service-detail') {
        $detail = service_model_find_detail($database, $serviceId);
        if ($detail === null) {
            api_controller_respond(['error' => 'Service introuvable'], 404);
        }
        api_controller_respond($detail, 200);
    }

    if ($api === 'booking-create' && $method === 'POST') {
        $created = service_model_create_booking(
            $database,
            $serviceId,
            $userId,
            (string) ($_POST['scheduled_at'] ?? ''),
            trim((string) ($_POST['message'] ?? ''))
        );
        if (!$created) {
            throw new RuntimeException('Date invalide ou service indisponible.');
        }
        api_controller_respond(['ok' => true], 200);
    }

    if ($api === 'review-create' && $method === 'POST') {
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new RuntimeException('Choisis une note entre 1 et 5 étoiles.');
        }
        if ($comment === '' || mb_strlen($comment) > 255) {
            throw new RuntimeException(
                'Ton avis doit contenir entre 1 et 255 caractères.'
            );
        }

        $created = service_model_create_review(
            $database,
            $serviceId,
            $userId,
            $rating,
            $comment
        );
        if (!$created) {
            throw new RuntimeException(
                'Un avis peut être publié une seule fois après un rendez-vous terminé.'
            );
        }
        api_controller_respond(['ok' => true], 200);
    }

    if ($api === 'contact' && $method === 'POST') {
        $conversationId = service_model_contact($database, $serviceId, $userId);
        if ($conversationId === null) {
            throw new RuntimeException('Contact indisponible.');
        }
        api_controller_respond([
            'redirect' => 'index.php?page=messages&conversation=' . $conversationId,
        ], 200);
    }

    return false;
}

function service_controller_validate_create(array $input, ?array $uploads): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $price = (float) ($input['price'] ?? -1);
    $unit = trim((string) ($input['unit'] ?? ''));

    if (
        $title === '' || $category === '' || $description === '' || $unit === ''
        || $price < 0 || $uploads === null
        || !isset($uploads['name']) || !is_array($uploads['name'])
    ) {
        throw new RuntimeException(
            'Vérifie les champs et ajoute au moins une image.'
        );
    }
    if (count($uploads['name']) < 1 || count($uploads['name']) > 8) {
        throw new RuntimeException('Ajoute entre 1 et 8 images.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $images = [];

    foreach ($uploads['name'] as $index => $originalName) {
        $error = (int) $uploads['error'][$index];
        $size = (int) $uploads['size'][$index];
        if ($error !== UPLOAD_ERR_OK || $size > 5 * 1024 * 1024) {
            throw new RuntimeException(
                'Chaque image doit peser au maximum 5 Mo.'
            );
        }

        $temporaryPath = (string) $uploads['tmp_name'][$index];
        $mimeType = $fileInfo->file($temporaryPath);
        $extension = $allowedTypes[$mimeType] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Formats acceptés : JPG, PNG ou WebP.');
        }

        $images[] = [
            'tmp' => $temporaryPath,
            'name' => (string) $originalName,
            'size' => $size,
            'ext' => $extension,
        ];
    }

    return [
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'price' => $price,
        'unit' => $unit,
        'images' => $images,
    ];
}
