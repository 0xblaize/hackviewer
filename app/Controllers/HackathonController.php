<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HackathonRepository;
use App\Services\CountdownService;

final class HackathonController
{
    public function __construct(private readonly HackathonRepository $repository, private readonly CountdownService $countdown)
    {
    }

    public function show(int $id): void
    {
        $hackathon = $this->repository->find($id);
        if (!$hackathon) {
            http_response_code(404);
            $pageTitle = 'Hackathon not found';
            require \appRoot() . '/app/Views/errors/404.php';
            return;
        }
        $links = $this->repository->links($id);
        $checks = $this->repository->checks($id);
        $pageTitle = $hackathon['title'];
        require \appRoot() . '/app/Views/hackathon-detail.php';
    }
}
