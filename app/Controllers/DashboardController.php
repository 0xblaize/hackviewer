<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DiscoveryCandidateRepository;
use App\Repositories\HackathonRepository;
use App\Services\CountdownService;

final class DashboardController
{
    public function __construct(private readonly HackathonRepository $repository, private readonly CountdownService $countdown, private readonly DiscoveryCandidateRepository $candidates)
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
        $listings = $this->repository->search($filters);
        $summary = $this->repository->summary();
        $options = $this->repository->options();
        $leads = $this->candidates->publicLeads();
        $countdown = $this->countdown;
        $pageTitle = 'Find the next worthwhile hackathon';
        require \appRoot() . '/app/Views/dashboard.php';
    }
}
