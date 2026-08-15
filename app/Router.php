<?php

declare(strict_types=1);

namespace App;

use App\Controllers\DashboardController;
use App\Controllers\HackathonController;
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
        if ($path === '/' || $path === '') {
            (new DashboardController($repository, $countdown))->index();
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
}
