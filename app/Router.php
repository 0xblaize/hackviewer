<?php

declare(strict_types=1);

namespace App;

use App\Controllers\DashboardController;
use App\Controllers\HackathonController;
use App\Repositories\HackathonRepository;
use App\Services\CountdownService;

final class Router
{
    public function dispatch(string $path): void
    {
        $repository = new HackathonRepository(Database::connection());
        $countdown = new CountdownService();
        if ($path === '/' || $path === '') {
            (new DashboardController($repository, $countdown))->index();
            return;
        }
        if (preg_match('#^/hackathons/(\d+)$#', $path, $matches)) {
            (new HackathonController($repository, $countdown))->show((int) $matches[1]);
            return;
        }
        if ($path === '/health') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'database' => is_file((string) \config('database'))]);
            return;
        }
        http_response_code(404);
        $pageTitle = 'Page not found';
        require \appRoot() . '/app/Views/errors/404.php';
    }
}
