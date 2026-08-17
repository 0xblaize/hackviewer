<?php

declare(strict_types=1);

namespace App;

use App\Controllers\DashboardController;
use App\Controllers\HackathonController;
use App\Api\Controllers\CandidateApiController;
use App\Api\Controllers\HackathonApiController;
use App\Api\Controllers\HealthApiController;
use App\Controllers\DiscoveryCandidateController;
use App\Repositories\HackathonRepository;
use App\Repositories\DiscoveryCandidateRepository;
use App\Services\CandidateReviewService;
use App\Services\CountdownService;

final class Router
{
    public function dispatch(string $path): void
    {
        $pdo = Database::connection();
        $repository = new HackathonRepository($pdo);
        $candidates = new DiscoveryCandidateRepository($pdo);
        $candidateController = new DiscoveryCandidateController($candidates, new CandidateReviewService($candidates));
        $countdown = new CountdownService();
        if (str_starts_with($path, '/api/v1')) {
            $this->dispatchApi($path, $pdo, $repository, $candidates);
            return;
        }
        if ($path === '/' || $path === '') {
            (new DashboardController($repository, $countdown, $candidates))->index();
            return;
        }
        if (preg_match('#^/hackathons/(\d+)$#', $path, $matches)) {
            (new HackathonController($repository, $countdown))->show((int) $matches[1]);
            return;
        }
        if ($path === '/candidates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $candidateController->index();
            return;
        }
        if (preg_match('#^/candidates/(\d+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $candidateController->show((int) $matches[1]);
            return;
        }
        if (preg_match('#^/candidates/(\d+)/(reject|convert)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $matches[2] === 'reject' ? $candidateController->reject((int) $matches[1]) : $candidateController->convert((int) $matches[1]);
            return;
        }
        if ($path === '/health') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'database' => is_file((string) \config('database')), 'schema' => (bool) $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='hackathons'")->fetchColumn()]);
            return;
        }
        http_response_code(404);
        $pageTitle = 'Page not found';
        require \appRoot() . '/app/Views/errors/404.php';
    }

    private function dispatchApi(string $path, \PDO $pdo, HackathonRepository $repository, DiscoveryCandidateRepository $candidates): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }
        if ($path === '/api/v1/health' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            (new HealthApiController($pdo))->show();
            return;
        }
        if ($path === '/api/v1/hackathons' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            (new HackathonApiController($repository, $candidates))->index();
            return;
        }
        if (preg_match('#^/api/v1/hackathons/(\d+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            (new HackathonApiController($repository, $candidates))->show((int) $matches[1]);
            return;
        }
        if (!$this->validApiToken()) {
            return;
        }
        $controller = new CandidateApiController($candidates, new CandidateReviewService($candidates));
        if ($path === '/api/v1/candidates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->index();
            return;
        }
        if (preg_match('#^/api/v1/candidates/(\d+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->show((int) $matches[1]);
            return;
        }
        if (preg_match('#^/api/v1/candidates/(\d+)/reject$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->reject((int) $matches[1]);
            return;
        }
        if (preg_match('#^/api/v1/candidates/(\d+)/convert$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->convert((int) $matches[1]);
            return;
        }
        \App\Api\JsonResponse::error('not_found', 'API route not found.', 404);
    }

    private function validApiToken(): bool
    {
        $expected = trim((string) \config('review_api_token', ''));
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $provided = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return true;
        }
        \App\Api\JsonResponse::error('unauthorized', 'API authentication required.', 401);
        return false;
    }
}
