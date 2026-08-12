<?php

declare(strict_types=1);

namespace App\Sources;

use RuntimeException;

final class RssAdapter implements SourceAdapter
{
    public function __construct(private readonly string $sourceKey, private readonly string $feedUrl)
    {
    }

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function fetch(): iterable
    {
        $context = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'Hackview/0.1 (+source-attributed discovery)']]);
        $payload = @file_get_contents($this->feedUrl, false, $context);
        if ($payload === false) {
            throw new RuntimeException('Unable to fetch public feed: ' . $this->feedUrl);
        }
        $xml = @simplexml_load_string($payload);
        if ($xml === false) {
            throw new RuntimeException('Feed response was not valid XML.');
        }
        foreach ($xml->channel->item ?? $xml->entry ?? [] as $item) {
            $link = (string) ($item->link['href'] ?? $item->link ?? '');
            if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            yield [
                'source_event_id' => (string) ($item->guid ?? $item->id ?? $link),
                'official_url' => $link,
                'canonical_url' => $link,
                'title' => trim((string) ($item->title ?? '')),
                'description' => trim(strip_tags((string) ($item->description ?? $item->summary ?? ''))),
                'start_at_utc' => null,
                'end_at_utc' => $this->parseDate((string) ($item->pubDate ?? $item->updated ?? '')),
                'source_url' => $this->feedUrl,
                'raw_title' => trim((string) ($item->title ?? '')),
            ];
        }
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('c', $timestamp);
    }
}
