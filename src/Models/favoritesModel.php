<?php
declare(strict_types=1);

final class FavoritesModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listServices(?int $userId = null): array
    {
        $sql = 'SELECT s.Id_services AS id, s.title, s.description, s.price, s.location,
                       c.name AS category, u.name AS provider_name,
                       f.Id_favorites IS NOT NULL AS is_favorite,
                       s.Id_users = :owner_id AS is_owner,
                       (SELECT m.file_path FROM Media m
                        WHERE m.Id_services = s.Id_services AND m.Id_events IS NULL
                        ORDER BY m.Id_media DESC LIMIT 1) AS photo_url
                FROM Services s
                JOIN Categories c ON c.Id_categories = s.Id_categories
                JOIN Users u ON u.Id_users = s.Id_users
                LEFT JOIN Favorites f ON f.Id_services = s.Id_services AND f.Id_users = :user_id
                WHERE s.status = \'active\'
                ORDER BY s.Id_services DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['user_id' => $userId, 'owner_id' => $userId]);
        return $statement->fetchAll();
    }

    public function listFavorites(int $userId): array
    {
        $sql = 'SELECT s.Id_services AS id, s.title, s.description, s.price, s.location,
                       c.name AS category, u.name AS provider_name, 1 AS is_favorite,
                       s.Id_users = :owner_id AS is_owner,
                       (SELECT m.file_path FROM Media m
                        WHERE m.Id_services = s.Id_services AND m.Id_events IS NULL
                        ORDER BY m.Id_media DESC LIMIT 1) AS photo_url
                FROM Favorites f
                JOIN Services s ON s.Id_services = f.Id_services
                JOIN Categories c ON c.Id_categories = s.Id_categories
                JOIN Users u ON u.Id_users = s.Id_users
                WHERE f.Id_users = :user_id
                ORDER BY f.created_at DESC, f.Id_favorites DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['user_id' => $userId, 'owner_id' => $userId]);
        return $statement->fetchAll();
    }

    public function add(int $userId, int $serviceId): bool
    {
        $check = $this->pdo->prepare(
            'SELECT 1 FROM Services WHERE Id_services = :service_id AND status = \'active\''
        );
        $check->execute(['service_id' => $serviceId]);
        if ($check->fetchColumn() === false) {
            return false;
        }

        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO Favorites (Id_services, Id_users) VALUES (:service_id, :user_id)'
        );
        $insert->execute(['service_id' => $serviceId, 'user_id' => $userId]);
        return $insert->rowCount() > 0;
    }

    public function remove(int $userId, int $serviceId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM Favorites WHERE Id_services = :service_id AND Id_users = :user_id'
        );
        $statement->execute(['service_id' => $serviceId, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public function listCategories(): array
    {
        return $this->pdo->query('SELECT Id_categories AS id, name FROM Categories ORDER BY name')
            ->fetchAll();
    }

    public function createService(int $userId, array $service): int
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO Services (title, price, location, status, description, Id_categories, Id_users)
                 SELECT :title, :price, :location, \'active\', :description, Id_categories, :user_id
                 FROM Categories WHERE Id_categories = :category_id'
            );
            $statement->execute([
                'title' => $service['title'],
                'price' => $service['price'],
                'location' => $service['location'] !== '' ? $service['location'] : null,
                'description' => $service['description'] !== '' ? $service['description'] : null,
                'user_id' => $userId,
                'category_id' => $service['category_id'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new InvalidArgumentException('Selected service category does not exist.');
            }

            $serviceId = (int) $this->pdo->lastInsertId();
            if ($service['photo_url'] !== '') {
                $media = $this->pdo->prepare(
                    'INSERT INTO Media (name_media, file_path, Id_services) VALUES (:name, :file_path, :service_id)'
                );
                $media->execute([
                    'name' => $service['title'],
                    'file_path' => $service['photo_url'],
                    'service_id' => $serviceId,
                ]);
            }
            $this->pdo->commit();
            return $serviceId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteOwnedService(int $userId, int $serviceId): array|false
    {
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare(
                'SELECT Id_services
                 FROM Services
                 WHERE Id_services = :service_id AND Id_users = :user_id
                 FOR UPDATE'
            );
            $find->execute(['service_id' => $serviceId, 'user_id' => $userId]);
            $service = $find->fetch();
            if ($service === false) {
                $this->pdo->rollBack();
                return false;
            }

            $photos = $this->pdo->prepare(
                'SELECT file_path FROM Media WHERE Id_services = :service_id AND Id_events IS NULL'
            );
            $photos->execute(['service_id' => $serviceId]);
            $service['photo_paths'] = $photos->fetchAll(PDO::FETCH_COLUMN);

            $delete = $this->pdo->prepare(
                'DELETE FROM Services WHERE Id_services = :service_id AND Id_users = :user_id'
            );
            $delete->execute(['service_id' => $serviceId, 'user_id' => $userId]);
            $this->pdo->commit();
            return $service;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
