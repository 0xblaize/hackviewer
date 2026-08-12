<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
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
                $status = !empty($record['end_at_utc']) && strtotime((string) $record['end_at_utc']) < time() ? 'closed' : 'upcoming';
                $stmt = $this->pdo->prepare('INSERT INTO hackathons (source_id, source_event_id, canonical_url, official_url, title, organizer_name, platform_name, description, hackathon_type, start_at_utc, end_at_utc, registration_deadline_utc, timezone_name, prize_text, participant_count, online_or_location, location_text, status, verification_status, last_seen_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(canonical_url) DO UPDATE SET title = excluded.title, organizer_name = excluded.organizer_name, platform_name = excluded.platform_name, description = excluded.description, hackathon_type = excluded.hackathon_type, start_at_utc = excluded.start_at_utc, end_at_utc = excluded.end_at_utc, registration_deadline_utc = excluded.registration_deadline_utc, timezone_name = excluded.timezone_name, prize_text = excluded.prize_text, participant_count = excluded.participant_count, online_or_location = excluded.online_or_location, location_text = excluded.location_text, last_seen_at = excluded.last_seen_at, updated_at = excluded.updated_at');
                $stmt->execute([$sourceId, $record['source_event_id'] ?? $record['canonical_url'], $record['canonical_url'], $record['official_url'], $record['title'], $record['organizer_name'] ?? null, $record['platform_name'] ?? $sourceName, $record['description'] ?? null, $record['hackathon_type'] ?? null, $record['start_at_utc'] ?? null, $record['end_at_utc'] ?? null, $record['registration_deadline_utc'] ?? null, $record['timezone_name'] ?? null, $record['prize_text'] ?? null, $record['participant_count'] ?? null, $record['online_or_location'] ?? null, $record['location_text'] ?? null, $status, 'unreviewed', $now, $now, $now]);
                $hackathonId = (int) $this->pdo->query('SELECT id FROM hackathons WHERE canonical_url = ' . $this->pdo->quote((string) $record['canonical_url']))->fetchColumn();
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
