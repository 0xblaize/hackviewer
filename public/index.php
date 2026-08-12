<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Router;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
(new Router())->dispatch(rtrim($path, '/') ?: '/');
