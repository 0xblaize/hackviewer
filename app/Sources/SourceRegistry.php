<?php

declare(strict_types=1);

namespace App\Sources;

final class SourceRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'devpost' => self::definition('Devpost', 'platform', 'DEVPOST_ENDPOINT_URL'),
            'dorahacks' => self::definition('DoraHacks', 'platform', 'DORAHACKS_ENDPOINT_URL'),
            'mlh' => self::definition('Major League Hacking', 'platform', 'MLH_ENDPOINT_URL'),
            'hackerearth' => self::definition('HackerEarth', 'platform', 'HACKEREARTH_ENDPOINT_URL'),
            'kaggle' => self::definition('Kaggle', 'platform', 'KAGGLE_ENDPOINT_URL'),
            'hackquest' => self::definition('HackQuest', 'platform', 'HACKQUEST_ENDPOINT_URL'),
            'unstop' => self::definition('Unstop', 'platform', 'UNSTOP_ENDPOINT_URL'),
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
    private static function definition(string $name, string $kind, string $endpointEnv): array
    {
        $endpoint = trim((string) config(strtolower($endpointEnv), ''));
        return [
            'name' => $name,
            'kind' => $kind,
            'endpoint_env' => $endpointEnv,
            'endpoint' => $endpoint,
            'configured' => $endpoint !== '',
            'status' => $endpoint === '' ? 'manual-only' : 'configured',
        ];
    }
}
