<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UrlNormalizer;
use PDO;
use Throwable;

final class DiscoveryPersister
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ingest(string $sourceKey, string $sourceName, string $kind, string $endpoint, iterable $records): int
    {
        $now = gmdate('c');
        $source = $this->pdo->prepare('INSERT INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(source_key) DO UPDATE SET name = excluded.name, kind = excluded.kind, base_url = excluded.base_url, updated_at = excluded.updated_at');
        $source->execute([$sourceKey, $sourceName, $kind, $endpoint, $now, $now]);
        $sourceId = (int) $this->pdo->query('SELECT id FROM sources WHERE source_key = ' . $this->pdo->quote($sourceKey))->fetchColumn();
        $run = $this->pdo->prepare('INSERT INTO ingestion_runs (source_id, started_at, status) VALUES (?, ?, ?)');
        $run->execute([$sourceId, $now, 'running']);
        $runId = (int) $this->pdo->lastInsertId();
        $count = 0;
        try {
            foreach ($records as $record) {
                if (($record['title'] ?? '') === '' || !filter_var($record['official_url'] ?? '', FILTER_VALIDATE_URL) || !str_starts_with(strtolower((string) $record['official_url']), 'https://')) {
                    continue;
                }
                $canonicalKey = UrlNormalizer::normalize((string) ($record['canonical_key'] ?? $record['canonical_url'] ?? $record['official_url']));
                $officialUrl = (string) $record['official_url'];
                $status = !empty($record['end_at_utc']) && strtotime((string) $record['end_at_utc']) < time() ? 'closed' : 'upcoming';
                $existing = $this->pdo->prepare('SELECT id FROM hackathons WHERE canonical_key = ? OR canonical_url = ? OR official_url = ? ORDER BY CASE WHEN verification_status = \'verified\' THEN 0 ELSE 1 END, id LIMIT 1');
                $existing->execute([$canonicalKey, $canonicalKey, $officialUrl]);
                $hackathonId = $existing->fetchColumn();
                if ($hackathonId === false) {
                    foreach ($this->pdo->query('SELECT id, canonical_key, canonical_url, official_url FROM hackathons')->fetchAll() as $candidate) {
                        $candidateKey = UrlNormalizer::normalize((string) ($candidate['canonical_key'] ?: $candidate['canonical_url'] ?: $candidate['official_url']));
                        if ($candidateKey === $canonicalKey) {
                            $hackathonId = $candidate['id'];
                            break;
                        }
                    }
                }
                $sourceEventId = $record['source_event_id'] ?? $canonicalKey;
                $title = (string) $record['title'];
                $organizer = $record['organizer_name'] ?? null;
                $platform = $record['platform_name'] ?? $sourceName;
                $description = $record['description'] ?? null;
                $type = $record['hackathon_type'] ?? null;
                $start = $record['start_at_utc'] ?? null;
                $end = $record['end_at_utc'] ?? null;
                $deadline = $record['registration_deadline_utc'] ?? null;
                $timezone = $record['timezone_name'] ?? null;
                $prize = $record['prize_text'] ?? null;
                $participants = $record['participant_count'] ?? null;
                $format = $record['online_or_location'] ?? null;
                $location = $record['location_text'] ?? null;
                if ($hackathonId === false) {
                    $stmt = $this->pdo->prepare('INSERT INTO hackathons (source_id, source_event_id, canonical_url, official_url, canonical_key, title, organizer_name, platform_name, description, hackathon_type, start_at_utc, end_at_utc, registration_deadline_utc, timezone_name, prize_text, participant_count, online_or_location, location_text, status, verification_status, last_seen_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'unreviewed\', ?, ?, ?)');
                    $stmt->execute([$sourceId, $sourceEventId, $canonicalKey, $officialUrl, $canonicalKey, $title, $organizer, $platform, $description, $type, $start, $end, $deadline, $timezone, $prize, $participants, $format, $location, $status, $now, $now, $now]);
                    $hackathonId = (int) $this->pdo->lastInsertId();
                } else {
                    $update = $this->pdo->prepare('UPDATE hackathons SET source_id = ?, source_event_id = ?, canonical_url = ?, official_url = ?, canonical_key = ?, title = ?, organizer_name = ?, platform_name = ?, description = ?, hackathon_type = ?, start_at_utc = ?, end_at_utc = ?, registration_deadline_utc = ?, timezone_name = ?, prize_text = ?, participant_count = ?, online_or_location = ?, location_text = ?, status = CASE WHEN verification_status = \'verified\' THEN status ELSE ? END, last_seen_at = ?, updated_at = ? WHERE id = ?');
                    $update->execute([$sourceId, $sourceEventId, $canonicalKey, $officialUrl, $canonicalKey, $title, $organizer, $platform, $description, $type, $start, $end, $deadline, $timezone, $prize, $participants, $format, $location, $status, $now, $now, (int) $hackathonId]);
                    $hackathonId = (int) $hackathonId;
                }
                foreach ($record['links'] ?? [] as $link) {
                    if (!isset($link['url']) || !filter_var($link['url'], FILTER_VALIDATE_URL)) {
                        continue;
                    }
                    $linkStmt = $this->pdo->prepare('INSERT INTO hackathon_links (hackathon_id, kind, url, label, created_at) SELECT ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM hackathon_links WHERE hackathon_id = ? AND url = ?)');
                    $linkStmt->execute([$hackathonId, $link['kind'] ?? 'related', $link['url'], $link['label'] ?? 'Source link', $now, $hackathonId, $link['url']]);
                }
                $count++;
            }
            $this->pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, fetched_count = ?, updated_count = ? WHERE id = ?')->execute([gmdate('c'), 'complete', $count, $count, $runId]);
            $this->pdo->prepare('UPDATE sources SET last_success_at = ?, updated_at = ? WHERE id = ?')->execute([$now, $now, $sourceId]);
            return $count;
        } catch (Throwable $error) {
            $this->pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, error_count = 1 WHERE id = ?')->execute([gmdate('c'), 'failed', $runId]);
            $this->pdo->prepare('UPDATE sources SET last_error_at = ?, updated_at = ? WHERE id = ?')->execute([gmdate('c'), gmdate('c'), $sourceId]);
            throw $error;
        }
    }
}
