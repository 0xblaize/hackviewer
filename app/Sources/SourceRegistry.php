<?php

declare(strict_types=1);

namespace App\Sources;

final class SourceRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'devpost' => self::definition('Devpost', 'platform', 'DEVPOST_ENDPOINT_URL', ['method' => 'GET']),
            'dorahacks' => self::definition('DoraHacks', 'platform', 'DORAHACKS_ENDPOINT_URL', ['method' => 'POST', 'payload' => ['page' => 1, 'size' => 10]]),
            'mlh' => self::definition('Major League Hacking', 'platform', 'MLH_ENDPOINT_URL', ['method' => 'GET']),
            'hackerearth' => self::definition('HackerEarth', 'platform', 'HACKEREARTH_ENDPOINT_URL', ['method' => 'GET']),
            'kaggle' => self::definition('Kaggle', 'platform', 'KAGGLE_ENDPOINT_URL', ['method' => 'GET', 'basic_auth' => ['username' => 'KAGGLE_USERNAME', 'password' => 'KAGGLE_API_KEY']]),
            'hackquest' => self::definition('HackQuest', 'platform', 'HACKQUEST_ENDPOINT_URL', ['method' => 'GET']),
            'unstop' => self::definition('Unstop', 'platform', 'UNSTOP_ENDPOINT_URL', ['method' => 'POST', 'payload' => ['opportunity' => 'hackathons', 'page' => 1, 'perPage' => 15]]),
            'sorsa' => self::definition('Sorsa posts', 'discovery', 'SORSA_SEARCH_ENDPOINT_URL'),
        ];
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        $sources = self::all();
        return $sources[$key] ?? throw new \InvalidArgumentException("Unknown source: {$key}");
    }

    /** @return array<string, mixed> */
    private static function definition(string $name, string $kind, string $endpointEnv, array $request = []): array
    {
        $endpoint = trim((string) config(strtolower($endpointEnv), ''));
        if (($request['basic_auth']['username'] ?? '') !== '') {
            $request['basic_auth'] = [
                'username' => trim((string) config(strtolower($request['basic_auth']['username']), '')),
                'password' => trim((string) config(strtolower($request['basic_auth']['password']), '')),
            ];
        }
        return [
            'name' => $name,
            'kind' => $kind,
            'endpoint_env' => $endpointEnv,
            'endpoint' => $endpoint,
            'request' => $request,
            'configured' => $endpoint !== '',
            'status' => $endpoint === '' ? 'manual-only' : 'configured',
        ];
    }
}
