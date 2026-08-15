<?php

declare(strict_types=1);

function appRoot(): string
{
    return dirname(__DIR__);
}

function config(string $key, mixed $default = null): mixed
{
    static $config;
    if ($config === null) {
        $config = [
            'database' => appRoot() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'app.sqlite',
            'timezone' => 'UTC',
            'x_bearer_token' => null,
            'x_api_base_url' => 'https://api.x.com/2',
            'x_search_query' => '(hackathon OR hackathons OR buildathon) -is:retweet lang:en',
            'sorsa_api_key' => null,
            'sorsa_api_base_url' => 'https://api.sorsa.io/v3',
            'sorsa_posts_endpoint_url' => null,
            'sorsa_search_endpoint_url' => 'https://api.sorsa.io/v3/search-tweets',
            'sorsa_search_query' => 'hackathon',
            'sorsa_search_query_field' => 'query',
            'sorsa_batch_queries' => [
                'hackathon',
                'buildathon',
                'hackathon prizes',
                'hackathon applications',
                'developer competition',
            ],
            'review_username' => null,
            'review_password' => null,
            'review_api_token' => null,
        ];

        $envPath = appRoot() . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $value = trim($value);
                $value = trim($value, "\\\"'");
                $config[strtolower($name)] = $value;
            }
        }

        $environmentKeys = [
            'app_timezone',
            'database_path',
            'review_username',
            'review_password',
            'review_api_token',
            'x_bearer_token',
            'x_api_base_url',
            'x_search_query',
            'sorsa_api_key',
            'sorsa_api_base_url',
            'sorsa_search_endpoint_url',
            'sorsa_search_query',
            'sorsa_search_query_field',
            'sorsa_batch_queries',
            'devpost_endpoint_url',
            'dorahacks_endpoint_url',
            'mlh_endpoint_url',
            'hackerearth_endpoint_url',
            'kaggle_endpoint_url',
            'hackquest_endpoint_url',
            'unstop_endpoint_url',
        ];
        foreach ($environmentKeys as $key) {
            $value = getenv(strtoupper($key));
            if ($value !== false && trim($value) !== '') {
                $config[$key] = $value;
            }
        }

        $databasePath = (string) ($config['database_path'] ?? $config['database']);
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $databasePath)) {
            $databasePath = appRoot() . DIRECTORY_SEPARATOR . $databasePath;
        }
        $config['database'] = $databasePath;
        $appTimezone = trim((string) ($config['app_timezone'] ?? ''));
        if ($appTimezone !== '') {
            $config['timezone'] = $appTimezone;
        }
        $config['x_bearer_token'] = $config['x_bearer_token'] ?? getenv('X_BEARER_TOKEN') ?: null;
        $config['sorsa_api_key'] = $config['sorsa_api_key'] ?? getenv('SORSA_API_KEY') ?: null;
        if (isset($config['sorsa_batch_queries']) && is_string($config['sorsa_batch_queries'])) {
            $decodedQueries = json_decode($config['sorsa_batch_queries'], true);
            if (is_array($decodedQueries)) {
                $config['sorsa_batch_queries'] = $decodedQueries;
            }
        }
    }

    return $config[$key] ?? $default;
}

$timezone = trim((string) config('timezone', 'UTC'));
if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = appRoot() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireReviewAuth(): bool
{
    $username = trim((string) config('review_username', ''));
    $password = (string) config('review_password', '');
    if ($username === '' || $password === '') {
        http_response_code(503);
        echo 'Candidate review authentication is not configured.';
        return false;
    }
    $providedUser = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $providedPassword = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
    if (!hash_equals($username, $providedUser) || !hash_equals($password, $providedPassword)) {
        header('WWW-Authenticate: Basic realm="Hackview candidate review"');
        http_response_code(401);
        echo 'Candidate review authentication required.';
        return false;
    }
    return true;
}

function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        throw new RuntimeException('Invalid form security token. Reload the page and try again.');
    }
}
