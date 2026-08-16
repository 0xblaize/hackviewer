<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\JsonResponse;
use App\Repositories\DiscoveryCandidateRepository;
use App\Repositories\HackathonRepository;

final class HackathonApiController
{
    public function __construct(private readonly HackathonRepository $repository, private readonly DiscoveryCandidateRepository $candidates)
    {
    }

    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'source' => trim((string) ($_GET['source'] ?? '')),
            'horizon' => trim((string) ($_GET['horizon'] ?? '')),
            'sort' => trim((string) ($_GET['sort'] ?? 'ending')),
        ];
        JsonResponse::send([
            'items' => $this->repository->search($filters),
            'summary' => $this->repository->summary(),
            'options' => $this->repository->options(),
            'leads' => $this->candidates->publicLeads(),
        ]);
    }

    public function show(int $id): void
    {
        $hackathon = $this->repository->find($id);
        if (!$hackathon) {
            JsonResponse::error('not_found', 'Hackathon not found.', 404);
            return;
        }
        JsonResponse::send([
            'hackathon' => $hackathon,
            'links' => $this->repository->links($id),
            'checks' => $this->repository->checks($id),
        ]);
    }
}
