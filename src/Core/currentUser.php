<?php
declare(strict_types=1);

function current_user_id(): int
{
    $id = $_SESSION['user_id'] ?? app_env('DB_PROFILE_USER_ID', '1');
    $validatedId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedId === false) {
        throw new RuntimeException('DB_PROFILE_USER_ID must be a positive integer.');
    }
    return $validatedId;
}
