<?php

declare(strict_types=1);

namespace App\Api;

final class JsonResponse
{
    public static function send(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $code, string $message, int $status, array $fields = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['code' => $code, 'message' => $message] + ($fields !== [] ? ['fields' => $fields] : [])], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
