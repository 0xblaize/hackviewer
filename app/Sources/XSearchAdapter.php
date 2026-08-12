<?php

declare(strict_types=1);

namespace App\Sources;

use RuntimeException;

final class XSearchAdapter implements SourceAdapter
{
    public function __construct(
        private readonly string $bearerToken,
        private readonly string $query,
        private readonly string $baseUrl = 'https://api.x.com/2'
    ) {
    }

    public function key(): string
    {
        return 'x-recent-search';
    }

    public function fetch(): iterable
    {
        $url = rtrim($this->baseUrl, '/') . '/tweets/search/recent?' . http_build_query([
            'query' => $this->query,
            'max_results' => 100,
            'tweet.fields' => 'created_at,author_id,public_metrics,lang',
            'expansions' => 'author_id',
            'user.fields' => 'username,name',
        ]);

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Authorization: Bearer {$this->bearerToken}\r\nAccept: application/json\r\nUser-Agent: Hackview/0.1 (+source-attributed discovery)\r\n",
        ]]);
        $payload = @file_get_contents($url, false, $context);
        $status = $this->responseStatus($http_response_header ?? []);
        if ($payload === false || $status >= 400) {
            throw new RuntimeException("X API request failed with HTTP {$status}.");
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('X API response was not valid JSON.');
        }

        $users = [];
        foreach ($decoded['includes']['users'] ?? [] as $user) {
            if (isset($user['id'])) {
                $users[(string) $user['id']] = $user;
            }
        }

        foreach ($decoded['data'] ?? [] as $post) {
            $id = (string) ($post['id'] ?? '');
            $text = trim((string) ($post['text'] ?? ''));
            if ($id === '' || $text === '') {
                continue;
            }
            $user = $users[(string) ($post['author_id'] ?? '')] ?? [];
            $handle = (string) ($user['username'] ?? '');
            yield [
                'external_key' => $id,
                'post_url' => $handle !== '' ? "https://x.com/{$handle}/status/{$id}" : "https://x.com/i/web/status/{$id}",
                'author_handle' => $handle,
                'text' => $text,
                'posted_at' => $post['created_at'] ?? null,
                'engagement' => $post['public_metrics'] ?? [],
            ];
        }
    }

    private function responseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }
}
