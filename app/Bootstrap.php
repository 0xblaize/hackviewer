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

        $databasePath = (string) ($config['database_path'] ?? $config['database']);
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $databasePath)) {
            $databasePath = appRoot() . DIRECTORY_SEPARATOR . $databasePath;
        }
        $config['database'] = $databasePath;
        $config['timezone'] = $config['app_timezone'] ?? $config['timezone'];
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

date_default_timezone_set((string) config('timezone', 'UTC'));

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
