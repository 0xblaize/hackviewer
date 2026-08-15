<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\JsonResponse;
use App\Repositories\DiscoveryCandidateRepository;
use App\Services\CandidateReviewService;
use Throwable;

final class CandidateApiController
{
    public function __construct(private readonly DiscoveryCandidateRepository $repository, private readonly CandidateReviewService $service)
    {
    }

    public function index(): void
    {
        JsonResponse::send(['items' => $this->repository->all()]);
    }

    public function show(int $id): void
    {
        $candidate = $this->repository->find($id);
        if (!$candidate) {
            JsonResponse::error('not_found', 'Candidate not found.', 404);
            return;
        }
        JsonResponse::send(['candidate' => $candidate]);
    }

    public function reject(int $id): void
    {
        $input = $this->input();
        try {
            $this->service->reject($id, trim((string) ($input['review_note'] ?? '')));
            JsonResponse::send(['status' => 'rejected']);
        } catch (Throwable $error) {
            $this->error($error);
        }
    }

    public function convert(int $id): void
    {
        try {
            $hackathonId = $this->service->convert($id, $this->input());
            JsonResponse::send(['status' => 'converted', 'hackathon_id' => $hackathonId], 201);
        } catch (Throwable $error) {
            $this->error($error);
        }
    }

    private function input(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function error(Throwable $error): void
    {
        $message = $error->getMessage();
        $status = str_contains(strtolower($message), 'missing') || str_contains(strtolower($message), 'already been reviewed') ? 409 : 422;
        JsonResponse::error($status === 409 ? 'conflict' : 'validation_error', $message, $status);
    }
}
