<?php

declare(strict_types=1);

function email_verification_model_find(
    PDO $database,
    string $purpose,
    ?int $userId,
    string $email
): ?array {
    $userCondition = $userId === null
        ? 'Id_users IS NULL'
        : 'Id_users = :user_id';
    $parameters = ['purpose' => $purpose, 'email' => $email];
    if ($userId !== null) {
        $parameters['user_id'] = $userId;
    }

    $query = $database->prepare(
        'SELECT * FROM Email_verification_codes '
        . 'WHERE purpose = :purpose AND email = :email AND '
        . $userCondition . ' ORDER BY Id_verifications DESC LIMIT 1'
    );
    $query->execute($parameters);

    return $query->fetch() ?: null;
}

function email_verification_model_store(
    PDO $database,
    ?int $userId,
    string $purpose,
    string $email,
    string $codeHash,
    array $pendingData
): void {
    $delete = $database->prepare(
        'DELETE FROM Email_verification_codes '
        . 'WHERE purpose = :purpose AND email = :email AND '
        . ($userId === null ? 'Id_users IS NULL' : 'Id_users = :user_id')
    );
    $parameters = ['purpose' => $purpose, 'email' => $email];
    if ($userId !== null) {
        $parameters['user_id'] = $userId;
    }
    $delete->execute($parameters);

    $insert = $database->prepare(
        'INSERT INTO Email_verification_codes '
        . '(Id_users, purpose, email, code_hash, pending_name, '
        . 'pending_first_name, pending_password, pending_email, expires_at, '
        . 'last_sent_at, attempts) VALUES '
        . '(:user_id, :purpose, :email, :code_hash, :pending_name, '
        . ':pending_first_name, :pending_password, :pending_email, '
        . ':expires_at, :last_sent_at, 0)'
    );
    $now = time();
    $insert->execute([
        'user_id' => $userId,
        'purpose' => $purpose,
        'email' => $email,
        'code_hash' => $codeHash,
        'pending_name' => $pendingData['name'] ?? null,
        'pending_first_name' => $pendingData['first_name'] ?? null,
        'pending_password' => $pendingData['password'] ?? null,
        'pending_email' => $pendingData['email'] ?? null,
        'expires_at' => gmdate('Y-m-d H:i:s', $now + 600),
        'last_sent_at' => gmdate('Y-m-d H:i:s', $now),
    ]);
}

function email_verification_model_delete(
    PDO $database,
    string $purpose,
    ?int $userId,
    string $email
): void {
    $userCondition = $userId === null
        ? 'Id_users IS NULL'
        : 'Id_users = :user_id';
    $parameters = ['purpose' => $purpose, 'email' => $email];
    if ($userId !== null) {
        $parameters['user_id'] = $userId;
    }

    $query = $database->prepare(
        'DELETE FROM Email_verification_codes '
        . 'WHERE purpose = :purpose AND email = :email AND '
        . $userCondition
    );
    $query->execute($parameters);
}

function email_verification_model_consume(
    PDO $database,
    string $purpose,
    ?int $userId,
    string $email,
    string $codeHash
): array {
    $database->beginTransaction();
    try {
        $userCondition = $userId === null
            ? 'Id_users IS NULL'
            : 'Id_users = :user_id';
        $parameters = ['purpose' => $purpose, 'email' => $email];
        if ($userId !== null) {
            $parameters['user_id'] = $userId;
        }

        $query = $database->prepare(
            'SELECT * FROM Email_verification_codes '
            . 'WHERE purpose = :purpose AND email = :email AND '
            . $userCondition . ' ORDER BY Id_verifications DESC LIMIT 1 FOR UPDATE'
        );
        $query->execute($parameters);
        $challenge = $query->fetch();

        if (!$challenge) {
            $database->commit();
            return ['status' => 'missing'];
        }

        if ((int) $challenge['attempts'] >= 5) {
            $database->commit();
            return ['status' => 'attempts_exhausted'];
        }

        $delete = $database->prepare(
            'DELETE FROM Email_verification_codes WHERE Id_verifications = :id'
        );
        $id = (int) $challenge['Id_verifications'];
        if (strtotime($challenge['expires_at'] . ' UTC') <= time()) {
            $database->commit();
            return ['status' => 'expired'];
        }

        if (!hash_equals((string) $challenge['code_hash'], $codeHash)) {
            $attempts = (int) $challenge['attempts'] + 1;
            $update = $database->prepare(
                'UPDATE Email_verification_codes SET attempts = :attempts '
                . 'WHERE Id_verifications = :id'
            );
            $update->execute(['attempts' => $attempts, 'id' => $id]);
            $database->commit();

            return $attempts >= 5
                ? ['status' => 'attempts_exhausted']
                : ['status' => 'invalid', 'remaining' => 5 - $attempts];
        }

        $delete->execute(['id' => $id]);
        $database->commit();

        return ['status' => 'verified', 'challenge' => $challenge];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $exception;
    }
}
