<?php

declare(strict_types=1);

namespace App\Sources;

use RuntimeException;

final class JsonAdapter implements SourceAdapter
{
    public function __construct(
        private readonly string $sourceKey,
        private readonly string $endpoint,
        private readonly array $mapping = [],
    ) {
    }

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function fetch(): iterable
    {
        if (!str_starts_with(strtolower($this->endpoint), 'https://')) {
            throw new RuntimeException('Source endpoints must use HTTPS.');
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: Hackview/0.1 (+source-attributed discovery)\r\n",
        ]]);
        $payload = @file_get_contents($this->endpoint, false, $context);
        $status = $this->responseStatus($http_response_header ?? []);
        if ($payload === false || $status >= 400) {
            throw new RuntimeException("JSON source request failed with HTTP {$status}.");
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON source response was not valid JSON.');
        }

        $items = $this->valueAt($decoded, (string) ($this->mapping['items_path'] ?? ''));
        if (!is_array($items)) {
            foreach (['data', 'items', 'events', 'results'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key])) {
                    $items = $decoded[$key];
                    break;
                }
            }
        }
        if (!is_array($items)) {
            throw new RuntimeException('JSON source item collection was not found. Configure items_path.');
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $officialUrl = $this->stringValue($item, 'official_url', 'url');
            $title = $this->stringValue($item, 'title', 'name');
            if ($officialUrl === '' || $title === '' || filter_var($officialUrl, FILTER_VALIDATE_URL) === false) {
                continue;
            }
            yield [
                'source_event_id' => $this->stringValue($item, 'source_event_id', 'id', 'slug') ?: $officialUrl,
                'official_url' => $officialUrl,
                'canonical_url' => $officialUrl,
                'title' => $title,
                'organizer_name' => $this->stringValue($item, 'organizer_name', 'organizer', 'host'),
                'platform_name' => $this->stringValue($item, 'platform_name', 'platform'),
                'description' => $this->stringValue($item, 'description', 'summary'),
                'hackathon_type' => $this->stringValue($item, 'hackathon_type', 'type', 'category'),
                'start_at_utc' => $this->dateValue($item, 'start_at_utc', 'start_date', 'start'),
                'end_at_utc' => $this->dateValue($item, 'end_at_utc', 'end_date', 'deadline', 'end'),
                'registration_deadline_utc' => $this->dateValue($item, 'registration_deadline_utc', 'registration_deadline', 'registration_deadline_date'),
                'timezone_name' => $this->stringValue($item, 'timezone_name', 'timezone'),
                'prize_text' => $this->stringValue($item, 'prize_text', 'prize', 'prizes'),
                'participant_count' => $this->integerValue($item, 'participant_count', 'participants'),
                'online_or_location' => $this->stringValue($item, 'online_or_location', 'format'),
                'location_text' => $this->stringValue($item, 'location_text', 'location', 'venue'),
                'source_url' => $this->endpoint,
                'links' => $this->links($item),
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

    private function valueAt(array $data, string $path): mixed
    {
        if ($path === '') {
            return $data;
        }
        $value = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function stringValue(array $item, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_scalar($item[$key])) {
                return trim((string) $item[$key]);
            }
        }
        return '';
    }

    private function dateValue(array $item, string ...$keys): ?string
    {
        $value = $this->stringValue($item, ...$keys);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('c', $timestamp);
    }

    private function integerValue(array $item, string ...$keys): ?int
    {
        $value = $this->stringValue($item, ...$keys);
        return $value !== '' && filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : null;
    }

    /** @return array<int, array<string, string>> */
    private function links(array $item): array
    {
        $links = [];
        foreach (['registration_url' => 'registration', 'rules_url' => 'rules', 'judging_url' => 'judging'] as $key => $kind) {
            $url = $this->stringValue($item, $key);
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $links[] = ['kind' => $kind, 'url' => $url, 'label' => ucfirst($kind)];
            }
        }
        return $links;
    }
}
