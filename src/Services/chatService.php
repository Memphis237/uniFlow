<?php

declare(strict_types=1);

function chat_ws_publish(array $event): array
{
    $secret = app_env('JWT_SECRET', '');
    if (strlen((string) $secret) < 32) {
        return [];
    }

    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $key = hash_hmac('sha256', 'uniflow-ws-internal', (string) $secret);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 0.35,
            'header' => "Content-Type: application/json\r\nX-UniFlow-Key: {$key}\r\n",
            'content' => $body,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents('http://127.0.0.1:8081/publish', false, $context);
    $data = is_string($response) ? json_decode($response, true) : null;

    return is_array($data) && isset($data['onlineUsers'])
        ? array_map('intval', $data['onlineUsers'])
        : [];
}

function chat_ticket(int $userId, int $conversationId): string
{
    $secret = (string) app_env('JWT_SECRET', '');
    $claims = [
        'user' => $userId,
        'conversation' => $conversationId,
        'exp' => time() + 600,
    ];
    $payload = rtrim(
        strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'),
        '='
    );

    return $payload . '.' . hash_hmac(
        'sha256',
        $payload,
        hash_hmac('sha256', 'uniflow-ws-ticket', $secret)
    );
}
