<?php

declare(strict_types=1);

namespace App\Sources;

use RuntimeException;

final class SorsaSearchAdapter
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $query,
        private readonly string $endpoint = 'https://api.sorsa.io/v3/search-tweets',
        private readonly string $queryField = 'query',
    ) {
    }

    /** @return iterable<array<string, mixed>> */
    public function fetch(): iterable
    {
        return $this->fetchResponse()['records'];
    }

    /** @return array{records: list<array<string, mixed>>, raw_body: string, http_status: int, content_type: string} */
    public function fetchResponse(): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('SORSA_API_KEY is not configured.');
        }
        if (!str_starts_with(strtolower($this->endpoint), 'https://')) {
            throw new RuntimeException('Sorsa endpoint must use HTTPS.');
        }

        $body = json_encode([$this->queryField => $this->query], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Unable to encode Sorsa search request.');
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "ApiKey: {$this->apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\nUser-Agent: Hackview/0.1 (+source-attributed discovery)\r\n",
            'content' => $body,
        ]]);
        $payload = @file_get_contents($this->endpoint, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = $this->responseStatus($responseHeaders);
        $contentType = $this->headerValue($responseHeaders, 'content-type');
        $rawBody = $payload === false ? '' : $payload;
        if ($payload === false || $status >= 400) {
            $message = $this->errorMessage($payload);
            throw new RuntimeException("Sorsa search failed with HTTP {$status}" . ($message !== '' ? ": {$message}" : '.'));
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Sorsa search response was not valid JSON.');
        }

        $items = $decoded;
        foreach (['data', 'tweets', 'results', 'posts'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $items = $decoded[$key];
                break;
            }
        }
        $records = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? $item['tweet_id'] ?? $item['post_id'] ?? '');
            $text = trim((string) ($item['text'] ?? $item['full_text'] ?? $item['content'] ?? ''));
            $url = trim((string) ($item['url'] ?? $item['tweet_url'] ?? $item['post_url'] ?? ''));
            if ($id === '' || $text === '') {
                continue;
            }
            if ($url === '') {
                $url = "https://x.com/i/web/status/{$id}";
            }
            $records[] = [
                'external_key' => $id,
                'post_url' => $url,
                'author_handle' => ltrim((string) ($item['author_username'] ?? $item['username'] ?? $item['handle'] ?? ''), '@'),
                'text' => $text,
                'posted_at' => $item['created_at'] ?? $item['posted_at'] ?? null,
                'engagement' => is_array($item['public_metrics'] ?? null) ? $item['public_metrics'] : [],
            ];
        }

        return ['records' => $records, 'raw_body' => $rawBody, 'http_status' => $status, 'content_type' => $contentType];
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

    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($name) . ':')) {
                return trim(substr($header, strlen($name) + 1));
            }
        }
        return '';
    }

    private function errorMessage(false|string $payload): string
    {
        if ($payload === false) {
            return '';
        }
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
        }
        return '';
    }
}
