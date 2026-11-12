<?php

declare(strict_types=1);

function service_catalog_model_search(
    PDO $pdo,
    int $viewerId,
    array $input,
    bool $searchEnabled = true,
    int $pageSize = 24,
    bool $favoritesOnly = false
): array {
    $pageSize = max(1, $pageSize);
    $filters = [
        'q' => '',
        'category' => 'all',
        'max_price' => 50,
        'sort' => 'recommended',
        'page' => 1,
    ];

    if ($searchEnabled) {
        $rawQuery = $input['q'] ?? '';
        $rawCategory = $input['category'] ?? 'all';
        $rawBudget = $input['max_price'] ?? '50';
        $rawSort = $input['sort'] ?? 'recommended';
        $rawPage = $input['page_number'] ?? '1';

        $categories = [
            'all',
            'coiffure',
            'cours',
            'demenagement',
            'informatique',
            'photo',
            'autres',
        ];
        $sorts = ['recommended', 'price-asc', 'rating-desc'];

        $filters['q'] = is_scalar($rawQuery)
            ? mb_substr(trim((string) $rawQuery), 0, 100)
            : '';
        $filters['category'] = is_scalar($rawCategory)
            && in_array((string) $rawCategory, $categories, true)
                ? (string) $rawCategory
                : 'all';
        $filters['max_price'] = is_scalar($rawBudget) && is_numeric($rawBudget)
            ? min(500, max(5, (float) $rawBudget))
            : 50;
        $filters['sort'] = is_scalar($rawSort)
            && in_array((string) $rawSort, $sorts, true)
                ? (string) $rawSort
                : 'recommended';
        $filters['page'] = is_scalar($rawPage) && ctype_digit((string) $rawPage)
            ? max(1, (int) $rawPage)
            : 1;
    } elseif ($favoritesOnly) {
        $rawPage = $input['page_number'] ?? '1';
        $filters['page'] = is_scalar($rawPage) && ctype_digit((string) $rawPage)
            ? max(1, (int) $rawPage)
            : 1;
    }

    $services = [];
    $total = 0;
    $pageCount = 1;

    try {
        $where = ["s.status = 'active'"];
        $params = [];

        if ($favoritesOnly) {
            $where[] = 'EXISTS(SELECT 1 FROM Favorites saved '
                . 'WHERE saved.Id_services = s.Id_services '
                . 'AND saved.Id_users = :favorite_viewer)';
            $params['favorite_viewer'] = $viewerId;
        }

        if ($searchEnabled && $filters['q'] !== '') {
            $where[] = "CONCAT_WS(' ', s.title, s.description, c.name, "
                . 'u.first_name, u.name, s.location, u.adresse, '
                . "u.postal_code, u.city, u.country) LIKE :search";
            $params['search'] = '%' . $filters['q'] . '%';
        }

        if ($searchEnabled) {
            $where[] = 's.price <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }

        if ($searchEnabled && $filters['category'] !== 'all') {
            $categoryQuery = $pdo->query(
                'SELECT Id_categories, name FROM Categories'
            );
            $ids = [];

            foreach ($categoryQuery->fetchAll() as $category) {
                if (
                    service_catalog_category_slug((string) $category['name'])
                    === $filters['category']
                ) {
                    $ids[] = (int) $category['Id_categories'];
                }
            }

            if ($ids) {
                $markers = [];
                foreach ($ids as $i => $id) {
                    $key = 'category_' . $i;
                    $markers[] = ':' . $key;
                    $params[$key] = $id;
                }
                $where[] = 's.Id_categories IN (' . implode(',', $markers) . ')';
            } else {
                $where[] = '1 = 0';
            }
        }

        $whereSql = implode(' AND ', $where);

        if ($searchEnabled || $favoritesOnly) {
            $count = $pdo->prepare(
                'SELECT COUNT(*) FROM Services s '
                . 'JOIN Categories c ON c.Id_categories = s.Id_categories '
                . 'JOIN Users u ON u.Id_users = s.Id_users '
                . 'WHERE ' . $whereSql
            );
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $pageCount = max(1, (int) ceil($total / $pageSize));
            $filters['page'] = min($filters['page'], $pageCount);
        }

        $order = match ($filters['sort']) {
            'price-asc' => 's.price ASC, s.Id_services DESC',
            'rating-desc' => 'rating IS NULL ASC, rating DESC, s.Id_services DESC',
            default => 's.Id_services DESC',
        };
        $offset = ($filters['page'] - 1) * $pageSize;

        $sql = "SELECT
            s.Id_services,
            s.title,
            s.price,
            s.description,
            s.unit,
            s.location,
            s.status,
            c.name AS category,
            u.first_name,
            u.name AS last_name,
            u.avatar AS owner_avatar,
            u.adresse,
            u.postal_code,
            u.city,
            u.country,
            CASE
                WHEN s.location IS NULL
                    OR s.location = ''
                    OR s.location = 'Campus Lyon'
                    OR s.location = CONCAT_WS(
                        ' ', NULLIF(u.city, ''), NULLIF(u.country, '')
                    )
                THEN NULLIF(
                    CONCAT_WS(
                        ', ',
                        NULLIF(u.adresse, ''),
                        NULLIF(u.postal_code, ''),
                        NULLIF(u.city, ''),
                        NULLIF(u.country, '')
                    ),
                    ''
                )
                ELSE s.location
            END AS resolved_location,
            (SELECT m.file_path
             FROM Media m
             WHERE m.Id_services = s.Id_services
             ORDER BY m.Id_media
             LIMIT 1) AS image,
            (SELECT COUNT(*)
             FROM Favorites f
             WHERE f.Id_services = s.Id_services) AS likes,
            EXISTS(
                SELECT 1
                FROM Favorites mine
                WHERE mine.Id_services = s.Id_services
                    AND mine.Id_users = :viewer
            ) AS liked,
            (SELECT ROUND(AVG(r.rating), 1)
             FROM Reviews r
             JOIN Bookings b ON b.Id_bookings = r.Id_bookings
             WHERE b.Id_services = s.Id_services) AS rating,
            (SELECT COUNT(*)
             FROM Reviews r
             JOIN Bookings b ON b.Id_bookings = r.Id_bookings
             WHERE b.Id_services = s.Id_services) AS reviews,
            (SELECT r.comment
             FROM Reviews r
             JOIN Bookings b ON b.Id_bookings = r.Id_bookings
             WHERE b.Id_services = s.Id_services
             ORDER BY r.created_at DESC
             LIMIT 1) AS comment,
            (SELECT CONCAT(ru.first_name, ' ', ru.name)
             FROM Reviews r
             JOIN Bookings b ON b.Id_bookings = r.Id_bookings
             JOIN Users ru ON ru.Id_users = r.Id_users
             WHERE b.Id_services = s.Id_services
             ORDER BY r.created_at DESC
             LIMIT 1) AS commenter
        FROM Services s
        JOIN Categories c ON c.Id_categories = s.Id_categories
        JOIN Users u ON u.Id_users = s.Id_users
        WHERE {$whereSql}
        ORDER BY {$order}
        LIMIT :limit OFFSET :offset";

        $query = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $query->bindValue(
                ':' . $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        $query->bindValue(':viewer', $viewerId, PDO::PARAM_INT);
        $query->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        foreach ($query->fetchAll() as $row) {
            $services[] = [
                'id' => (int) $row['Id_services'],
                'category' => service_catalog_category_slug(
                    (string) $row['category']
                ),
                'label' => $row['category'],
                'title' => $row['title'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'avatar' => $row['owner_avatar'] ?? '',
                'study' => 'Etudiant UniFlow',
                'price' => (float) $row['price'],
                'unit' => $row['unit'],
                'rating' => $row['rating'] !== null
                    ? number_format((float) $row['rating'], 1, '.', '')
                    : '0.0',
                'reviews' => (int) $row['reviews'],
                'location' => $row['resolved_location'] ?: 'Campus Lyon',
                'image' => $row['image'] ?: '',
                'tag' => '',
                'likes' => (int) $row['likes'],
                'liked' => (bool) $row['liked'],
                'comment' => $row['comment'] ?? '',
                'commenter' => $row['commenter'] ?? '',
                'description' => $row['description'],
            ];
        }
    } catch (Throwable $exception) {
        error_log(
            'Service catalog query failed: ' . $exception->getMessage()
        );
    }

    return [
        'services' => $services,
        'filters' => $filters,
        'total' => $total,
        'pageCount' => $pageCount,
    ];
}
