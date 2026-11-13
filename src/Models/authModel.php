<?php

declare(strict_types=1);

function auth_model_find_active_by_id(PDO $database, int $userId): ?array
{
    $query = $database->prepare(
        'SELECT Id_users, name, first_name, email, role, adresse, postal_code, '
        . 'city, country, phone, avatar FROM Users '
        . 'WHERE Id_users = :id AND status = :status LIMIT 1'
    );
    $query->execute(['id' => $userId, 'status' => 'active']);

    return $query->fetch() ?: null;
}

function auth_model_find_active_by_email(PDO $database, string $email): ?array
{
    $query = $database->prepare(
        'SELECT Id_users, name, first_name, email, password, role FROM Users '
        . 'WHERE email = :email AND status = :status LIMIT 1'
    );
    $query->execute(['email' => $email, 'status' => 'active']);

    return $query->fetch() ?: null;
}

function auth_model_email_exists(PDO $database, string $email): bool
{
    $query = $database->prepare(
        'SELECT Id_users FROM Users WHERE email = :email LIMIT 1'
    );
    $query->execute(['email' => $email]);

    return (bool) $query->fetchColumn();
}

function auth_model_create_student(
    PDO $database,
    string $name,
    string $firstName,
    string $email,
    string $passwordHash
): int {
    $query = $database->prepare(
        'INSERT INTO Users (name, first_name, email, password, role, status) '
        . 'VALUES (:name, :first_name, :email, :password, :role, :status)'
    );
    $query->execute([
        'name' => $name,
        'first_name' => $firstName,
        'email' => $email,
        'password' => $passwordHash,
        'role' => 'student',
        'status' => 'active',
    ]);

    return (int) $database->lastInsertId();
}

function auth_model_update_password(
    PDO $database,
    int $userId,
    string $passwordHash
): void {
    $query = $database->prepare(
        'UPDATE Users SET password = :password WHERE Id_users = :id'
    );
    $query->execute(['password' => $passwordHash, 'id' => $userId]);
}

function auth_model_update_email(
    PDO $database,
    int $userId,
    string $email
): void {
    $query = $database->prepare(
        'UPDATE Users SET email = :email WHERE Id_users = :id'
    );
    $query->execute(['email' => $email, 'id' => $userId]);
}
