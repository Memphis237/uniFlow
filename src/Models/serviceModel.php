<?php

declare(strict_types=1);

function service_model_create(PDO $database, array $actor, array $data): void
{
    $categoryId = service_model_category_id($database, $data['category']);
    $location = implode(', ', array_filter(array_map('trim', [
        (string) ($actor['adresse'] ?? ''),
        (string) ($actor['postal_code'] ?? ''),
        (string) ($actor['city'] ?? ''),
        (string) ($actor['country'] ?? ''),
    ])));
    if ($location === '') {
        $location = 'Campus Lyon';
    }

    $insertService = $database->prepare(
        'INSERT INTO Services '
        . '(title, price, description, unit, location, Id_categories, Id_users) '
        . 'VALUES (:title, :price, :description, :unit, :location, :category, :user)'
    );
    $insertService->execute([
        'title' => $data['title'],
        'price' => $data['price'],
        'description' => $data['description'],
        'unit' => $data['unit'],
        'location' => $location,
        'category' => $categoryId,
        'user' => $actor['Id_users'],
    ]);
    $serviceId = (int) $database->lastInsertId();

    $directory = dirname(__DIR__, 2) . '/public/uploads/services';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Impossible de préparer le stockage des images.');
    }

    $insertMedia = $database->prepare(
        'INSERT INTO Media(name_media, file_path, file_size, Id_services) '
        . 'VALUES (:name, :path, :size, :service)'
    );
    foreach ($data['images'] as $image) {
        $filename = bin2hex(random_bytes(16)) . '.' . $image['ext'];
        if (!move_uploaded_file($image['tmp'], $directory . '/' . $filename)) {
            throw new RuntimeException('Une image n’a pas pu être enregistrée.');
        }
        $insertMedia->execute([
            'name' => $image['name'],
            'path' => 'uploads/services/' . $filename,
            'size' => $image['size'],
            'service' => $serviceId,
        ]);
    }
}

function service_model_category_id(PDO $database, string $category): int
{
    $query = $database->prepare(
        'SELECT Id_categories FROM Categories WHERE name = :name LIMIT 1'
    );
    $query->execute(['name' => $category]);
    $categoryId = $query->fetchColumn();

    if ($categoryId) {
        return (int) $categoryId;
    }

    $insert = $database->prepare('INSERT INTO Categories(name) VALUES (:name)');
    $insert->execute(['name' => $category]);

    return (int) $database->lastInsertId();
}

function service_model_toggle_favorite(
    PDO $database,
    int $serviceId,
    int $userId
): array {
    $insert = $database->prepare(
        'INSERT IGNORE INTO Favorites(Id_services, Id_users) '
        . 'VALUES (:service, :user)'
    );
    $insert->execute(['service' => $serviceId, 'user' => $userId]);

    if (!$insert->rowCount()) {
        $delete = $database->prepare(
            'DELETE FROM Favorites '
            . 'WHERE Id_services = :service AND Id_users = :user'
        );
        $delete->execute(['service' => $serviceId, 'user' => $userId]);
    }

    $count = $database->prepare(
        'SELECT COUNT(*) FROM Favorites WHERE Id_services = :service'
    );
    $count->execute(['service' => $serviceId]);
    $liked = $database->prepare(
        'SELECT EXISTS(SELECT 1 FROM Favorites '
        . 'WHERE Id_services = :service AND Id_users = :user)'
    );
    $liked->execute(['service' => $serviceId, 'user' => $userId]);

    return [
        'likes' => (int) $count->fetchColumn(),
        'liked' => (bool) $liked->fetchColumn(),
    ];
}

function service_model_find_detail(PDO $database, int $serviceId): ?array
{
    $query = $database->prepare(
        'SELECT s.*, u.first_name, u.name, '
        . 'COALESCE((SELECT m.file_path FROM Media m '
        . 'WHERE m.Id_services = s.Id_services ORDER BY m.Id_media LIMIT 1), '
        . "'') AS image, "
        . '(SELECT COUNT(*) FROM Favorites f '
        . 'WHERE f.Id_services = s.Id_services) AS likes '
        . 'FROM Services s JOIN Users u ON u.Id_users = s.Id_users '
        . 'WHERE s.Id_services = :id LIMIT 1'
    );
    $query->execute(['id' => $serviceId]);
    $service = $query->fetch();
    if (!$service) {
        return null;
    }

    $media = $database->prepare(
        'SELECT file_path FROM Media WHERE Id_services = :id ORDER BY Id_media'
    );
    $media->execute(['id' => $serviceId]);
    $service['images'] = array_column($media->fetchAll(), 'file_path');
    if ($service['images'] === [] && $service['image'] !== '') {
        $service['images'] = [$service['image']];
    }

    $reviews = $database->prepare(
        'SELECT r.rating, r.comment, r.created_at, u.first_name, u.name '
        . 'FROM Reviews r JOIN Users u ON u.Id_users = r.Id_users '
        . 'JOIN Bookings b ON b.Id_bookings = r.Id_bookings '
        . 'WHERE b.Id_services = :id ORDER BY r.created_at DESC'
    );
    $reviews->execute(['id' => $serviceId]);

    return ['service' => $service, 'reviews' => $reviews->fetchAll()];
}

function service_model_create_booking(
    PDO $database,
    int $serviceId,
    int $userId,
    string $scheduledAt,
    string $message
): bool {
    $query = $database->prepare(
        'INSERT INTO Bookings(scheduled_at, message, Id_services, Id_users) '
        . 'SELECT :date, :message, Id_services, :user FROM Services '
        . 'WHERE Id_services = :service AND Id_users <> :owner'
    );
    $query->execute([
        'date' => $scheduledAt,
        'message' => $message,
        'user' => $userId,
        'service' => $serviceId,
        'owner' => $userId,
    ]);

    return $query->rowCount() > 0;
}

function service_model_create_review(
    PDO $database,
    int $serviceId,
    int $userId,
    int $rating,
    string $comment
): bool {
    $query = $database->prepare(
        "INSERT INTO Reviews(rating, comment, Id_bookings, Id_users)
         SELECT :rating, :comment, b.Id_bookings, :user
         FROM Bookings b
         WHERE b.Id_services = :service
            AND b.Id_users = :reviewer
            AND b.status = 'completed'
            AND NOT EXISTS (
                SELECT 1 FROM Reviews existing
                WHERE existing.Id_bookings = b.Id_bookings
            )
         ORDER BY b.Id_bookings
         LIMIT 1"
    );
    $query->execute([
        'rating' => $rating,
        'comment' => $comment,
        'user' => $userId,
        'service' => $serviceId,
        'reviewer' => $userId,
    ]);

    return $query->rowCount() > 0;
}

function service_model_contact(PDO $database, int $serviceId, int $userId): ?int
{
    $ownerQuery = $database->prepare(
        'SELECT Id_users FROM Services WHERE Id_services = :id'
    );
    $ownerQuery->execute(['id' => $serviceId]);
    $ownerId = (int) $ownerQuery->fetchColumn();
    if (!$ownerId || $ownerId === $userId) {
        return null;
    }

    $database->beginTransaction();
    try {
        $query = $database->prepare(
            'SELECT l1.Id_conversations FROM lier l1 '
            . 'JOIN lier l2 ON l1.Id_conversations = l2.Id_conversations '
            . 'WHERE l1.Id_users = :user AND l2.Id_users = :owner LIMIT 1'
        );
        $query->execute(['user' => $userId, 'owner' => $ownerId]);
        $conversationId = (int) $query->fetchColumn();

        if (!$conversationId) {
            $database->exec('INSERT INTO Conversations() VALUES()');
            $conversationId = (int) $database->lastInsertId();
            $link = $database->prepare(
                'INSERT INTO lier(Id_users, Id_conversations) '
                . 'VALUES (:user, :conversation)'
            );
            $link->execute(['user' => $userId, 'conversation' => $conversationId]);
            $link->execute(['user' => $ownerId, 'conversation' => $conversationId]);
        }

        $database->commit();

        return $conversationId;
    } catch (Throwable $exception) {
        $database->rollBack();
        throw $exception;
    }
}
