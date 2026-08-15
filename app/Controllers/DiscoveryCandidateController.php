<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DiscoveryCandidateRepository;
use App\Services\CandidateReviewService;
use Throwable;

final class DiscoveryCandidateController
{
    public function __construct(private readonly DiscoveryCandidateRepository $repository, private readonly CandidateReviewService $service)
    {
    }

    public function index(): void
    {
        $candidates = $this->repository->all();
        $pageTitle = 'Review candidates';
        require \appRoot() . '/app/Views/discovery-candidates.php';
    }

    public function show(int $id): void
    {
        $candidate = $this->repository->find($id);
        if (!$candidate) {
            http_response_code(404);
            $pageTitle = 'Candidate not found';
            require \appRoot() . '/app/Views/errors/404.php';
            return;
        }
        $pageTitle = 'Review candidate';
        require \appRoot() . '/app/Views/discovery-candidate-detail.php';
    }

    public function reject(int $id): void
    {
        try {
            \verifyCsrfToken($_POST['csrf_token'] ?? null);
            $this->service->reject($id, trim((string) ($_POST['review_note'] ?? '')));
            header('Location: /candidates?message=rejected');
        } catch (Throwable $error) {
            http_response_code(422);
            $errorMessage = $error->getMessage();
            $candidate = $this->repository->find($id);
            $pageTitle = 'Review candidate';
            require \appRoot() . '/app/Views/discovery-candidate-detail.php';
        }
    }

    public function convert(int $id): void
    {
        try {
            \verifyCsrfToken($_POST['csrf_token'] ?? null);
            $hackathonId = $this->service->convert($id, $_POST);
            header('Location: /hackathons/' . $hackathonId . '?message=converted');
        } catch (Throwable $error) {
            http_response_code(422);
            $errorMessage = $error->getMessage();
            $candidate = $this->repository->find($id);
            $pageTitle = 'Review candidate';
            require \appRoot() . '/app/Views/discovery-candidate-detail.php';
        }
    }
}
