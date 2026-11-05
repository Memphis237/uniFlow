<?php
declare(strict_types=1);

require_once __DIR__ . '/../Services/authService.php';

function current_user_id(): int
{
    if (isset($_SESSION['user_id'])) {
        $id = $_SESSION['user_id'];
    } elseif (isset($_COOKIE['uniflow_token'])) {
        $user = auth_user_from_cookie();
        if ($user === null) {
            throw new RuntimeException('The current user session is invalid or expired.');
        }
        $id = $user['Id_users'];
    } else {
        $id = app_env('DB_PROFILE_USER_ID', '1');
    }

    $validatedId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedId === false) {
        throw new RuntimeException('DB_PROFILE_USER_ID must be a positive integer.');
    }
    return $validatedId;
}
