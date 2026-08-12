<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Sources\RssAdapter;

$feedUrl = $argv[1] ?? null;
if (!$feedUrl || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php bin/ingest.php https://example.com/public-feed.xml\n");
    exit(1);
}

$pdo = Database::connection();
$adapter = new RssAdapter('public-feed', $feedUrl);
$now = gmdate('c');
$source = $pdo->prepare('INSERT INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(source_key) DO UPDATE SET base_url = excluded.base_url, updated_at = excluded.updated_at');
$source->execute([$adapter->key(), 'Public feed', 'rss', $feedUrl, $now, $now]);
$sourceId = (int) $pdo->query("SELECT id FROM sources WHERE source_key = 'public-feed'")->fetchColumn();
$run = $pdo->prepare('INSERT INTO ingestion_runs (source_id, started_at, status) VALUES (?, ?, ?)');
$run->execute([$sourceId, $now, 'running']);
$runId = (int) $pdo->lastInsertId();
$count = 0;
try {
    foreach ($adapter->fetch() as $record) {
        if (($record['title'] ?? '') === '' || !filter_var($record['official_url'] ?? '', FILTER_VALIDATE_URL)) {
            continue;
        }
        $stmt = $pdo->prepare('INSERT INTO hackathons (source_id, source_event_id, canonical_url, official_url, title, description, end_at_utc, status, verification_status, last_seen_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(canonical_url) DO UPDATE SET title = excluded.title, description = excluded.description, end_at_utc = excluded.end_at_utc, last_seen_at = excluded.last_seen_at, updated_at = excluded.updated_at');
        $status = !empty($record['end_at_utc']) && strtotime((string) $record['end_at_utc']) < time() ? 'closed' : 'upcoming';
        $stmt->execute([$sourceId, $record['source_event_id'], $record['canonical_url'], $record['official_url'], $record['title'], $record['description'], $record['end_at_utc'], $status, 'unreviewed', $now, $now, $now]);
        $count++;
    }
    $finish = $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, fetched_count = ?, updated_count = ? WHERE id = ?');
    $finish->execute([gmdate('c'), 'complete', $count, $count, $runId]);
    $pdo->prepare("UPDATE sources SET last_success_at = ?, updated_at = ? WHERE id = ?")->execute([$now, $now, $sourceId]);
    fwrite(STDOUT, "Ingested {$count} records. They remain unreviewed until verified.\n");
} catch (Throwable $error) {
    $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, error_count = 1 WHERE id = ?')->execute([gmdate('c'), 'failed', $runId]);
    $pdo->prepare("UPDATE sources SET last_error_at = ?, updated_at = ? WHERE id = ?")->execute([$now, $now, $sourceId]);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
