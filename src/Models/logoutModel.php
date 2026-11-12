<?php

declare(strict_types=1);

function logout_model_mark_offline(PDO $database, int $userId): array
{
    $database->prepare(
        'UPDATE Users SET is_online = 0, last_seen = NOW() WHERE Id_users = :id'
    )->execute(['id' => $userId]);

    $query = $database->prepare(
        'SELECT Id_conversations FROM lier WHERE Id_users = :id'
    );
    $query->execute(['id' => $userId]);

    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
}
