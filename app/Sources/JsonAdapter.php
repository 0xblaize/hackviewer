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

        $method = strtoupper((string) ($this->mapping['method'] ?? 'GET'));
        $headers = [
            'Accept: application/json',
            'User-Agent: Hackview/0.1 (+source-attributed discovery)',
        ];
        $auth = $this->mapping['basic_auth'] ?? null;
        if (is_array($auth) && ($auth['username'] ?? '') !== '' && ($auth['password'] ?? '') !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($auth['username'] . ':' . $auth['password']);
        }
        $requestBody = null;
        if ($method !== 'GET') {
            $requestBody = json_encode($this->mapping['payload'] ?? [], JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $requestBody,
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

        $itemsPath = (string) ($this->mapping['items_path'] ?? '');
        $items = $itemsPath === '' ? $this->findItems($decoded) : $this->valueAt($decoded, $itemsPath);
        if (!is_array($items)) {
            $items = $this->findItems($decoded);
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
            $start = $this->dateValue($item, 'start_at_utc', 'start_date', 'start');
            $end = $this->dateValue($item, 'end_at_utc', 'end_date', 'deadline', 'end');
            if ($this->sourceKey === 'devpost') {
                [$start, $end] = $this->dateRangeValue($this->stringValue($item, 'submission_period_dates'), $start, $end);
            }
            $themes = is_array($item['themes'] ?? null) && isset($item['themes'][0]['name']) ? (string) $item['themes'][0]['name'] : '';
            $prize = $this->stringValue($item, 'prize_text', 'prize', 'prizes');
            if ($prize === '' && isset($item['prize_amount']) && is_scalar($item['prize_amount'])) {
                $prize = trim(strip_tags((string) $item['prize_amount']));
            }
            yield [
                'source_event_id' => $this->stringValue($item, 'source_event_id', 'id', 'slug') ?: $officialUrl,
                'official_url' => $officialUrl,
                'canonical_url' => $officialUrl,
                'title' => $title,
                'organizer_name' => $this->stringValue($item, 'organizer_name', 'organizer', 'host', 'organization_name'),
                'platform_name' => $this->stringValue($item, 'platform_name', 'platform') ?: ($this->sourceKey === 'devpost' ? 'Devpost' : ''),
                'description' => $this->stringValue($item, 'description', 'summary'),
                'hackathon_type' => $this->stringValue($item, 'hackathon_type', 'type', 'category') ?: $themes,
                'start_at_utc' => $start,
                'end_at_utc' => $end,
                'registration_deadline_utc' => $end,
                'timezone_name' => $this->stringValue($item, 'timezone_name', 'timezone'),
                'prize_text' => $prize,
                'participant_count' => $this->integerValue($item, 'participant_count', 'participants', 'registrations_count'),
                'online_or_location' => $this->stringValue($item, 'online_or_location', 'format') ?: ($this->sourceKey === 'devpost' ? $this->stringValue((array) ($item['displayed_location'] ?? []), 'location') : ''),
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

    private function findItems(array $data): ?array
    {
        $preferred = ['data', 'items', 'events', 'results', 'opportunities', 'hackathons', 'competitions'];
        foreach ($preferred as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (is_array($value) && ($value === [] || array_is_list($value))) {
                return $value;
            }
            if (is_array($value)) {
                $nested = $this->findItems($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return array_is_list($data) ? $data : null;
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

    /** @return array{0: ?string, 1: ?string} */
    private function dateRangeValue(string $value, ?string $start, ?string $end): array
    {
        if ($value === '' || ($start !== null && $end !== null)) {
            return [$start, $end];
        }
        if (!preg_match('/([A-Za-z]{3,9})\s+(\d{1,2})\s*[-–]\s*(?:(?:([A-Za-z]{3,9})\s+))?(\d{1,2}),?\s*(20\d{2})/', $value, $match)) {
            return [$start, $end];
        }
        $year = (int) $match[5];
        $startMonth = date('m', strtotime($match[1] . ' 1 ' . $year));
        $endMonth = date('m', strtotime(($match[3] !== '' ? $match[3] : $match[1]) . ' 1 ' . $year));
        $startTime = strtotime(sprintf('%04d-%s-%02d 00:00:00 UTC', $year, $startMonth, (int) $match[2]));
        $endTime = strtotime(sprintf('%04d-%s-%02d 23:59:59 UTC', $year, $endMonth, (int) $match[4]));
        return [$start ?? ($startTime === false ? null : gmdate('c', $startTime)), $end ?? ($endTime === false ? null : gmdate('c', $endTime))];
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
