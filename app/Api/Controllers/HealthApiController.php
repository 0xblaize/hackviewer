<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\JsonResponse;
use PDO;

final class HealthApiController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function show(): void
    {
        JsonResponse::send([
            'status' => 'ok',
            'database' => is_file((string) \config('database')),
            'schema' => (bool) $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='hackathons'")->fetchColumn(),
        ]);
    }
}
