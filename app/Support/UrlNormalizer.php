<?php

declare(strict_types=1);

namespace App\Support;

final class UrlNormalizer
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') === '' || ($parts['host'] ?? '') === '') {
            return strtolower(rtrim($url, '/'));
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true) ? ':' . (int) $parts['port'] : '';
        $path = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '/')) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            $lower = strtolower((string) $key);
            if (str_starts_with($lower, 'utm_') || in_array($lower, ['ref', 'referrer', 'source', 'campaign', 'mc_cid', 'mc_eid'], true)) {
                unset($query[$key]);
            }
        }
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $scheme . '://' . $host . $port . $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    public static function textKey(string $text): string
    {
        $text = preg_replace('~https?://\S+~i', ' ', $text) ?? $text;
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
        $text = preg_replace('/[^\p{L}\p{N}\s$€£₹%.-]/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
