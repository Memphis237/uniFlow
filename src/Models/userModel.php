<?php
declare(strict_types=1);

final class UserModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_users AS id, name AS nom, `prénom` AS prenom, email,
                    formation, ecole, biographie AS bio, avatar
             FROM Users
             WHERE Id_users = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    public function updateProfile(int $id, array $profile): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Users
             SET name = :nom,
                 `prénom` = :prenom,
                 email = :email,
                 formation = :formation,
                 ecole = :ecole,
                 biographie = :bio,
                 avatar = :avatar
             WHERE Id_users = :id'
        );
        $statement->execute([
            'nom' => $profile['nom'],
            'prenom' => $profile['prenom'],
            'email' => $profile['email'],
            'formation' => $profile['formation'] !== '' ? $profile['formation'] : null,
            'ecole' => $profile['ecole'] !== '' ? $profile['ecole'] : null,
            'bio' => $profile['bio'] !== '' ? $profile['bio'] : null,
            'avatar' => $profile['avatar'] !== '' ? $profile['avatar'] : null,
            'id' => $id,
        ]);
    }
}