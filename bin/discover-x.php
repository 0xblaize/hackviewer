<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Sources\XSearchAdapter;

$token = (string) config('x_bearer_token', '');
$query = $argv[1] ?? (string) config('x_search_query', '(hackathon OR hackathons OR buildathon) -is:retweet lang:en');
if ($token === '') {
    fwrite(STDERR, "Set X_BEARER_TOKEN in .env before running discovery.\n");
    exit(1);
}

$pdo = Database::connection();
$now = gmdate('c');
$source = $pdo->prepare('INSERT INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(source_key) DO UPDATE SET updated_at = excluded.updated_at');
$source->execute(['x-recent-search', 'X recent search', 'social-api', 'https://x.com/search', $now, $now]);
$sourceId = (int) $pdo->query("SELECT id FROM sources WHERE source_key = 'x-recent-search'")->fetchColumn();

$run = $pdo->prepare('INSERT INTO ingestion_runs (source_id, started_at, status) VALUES (?, ?, ?)');
$run->execute([$sourceId, $now, 'running']);
$runId = (int) $pdo->lastInsertId();

try {
    $adapter = new XSearchAdapter($token, $query, (string) config('x_api_base_url', 'https://api.x.com/2'));
    $records = iterator_to_array($adapter->fetch());
    $raw = json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($raw === false) {
        throw new RuntimeException('Unable to encode X records.');
    }
    $hash = hash('sha256', $raw);
    $relativePath = 'x-search/' . gmdate('Ymd-His') . '-' . $hash . '.json';
    $absolutePath = appRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'raw' . DIRECTORY_SEPARATOR . $relativePath;
    if (!is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0775, true);
    }
    file_put_contents($absolutePath, $raw, LOCK_EX);

    $rawStmt = $pdo->prepare('INSERT INTO raw_ingestion_records (source_id, external_key, request_url, retrieved_at, http_status, content_type, content_hash, payload_path, parser_version, parse_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $rawStmt->execute([$sourceId, $hash, 'https://api.x.com/2/tweets/search/recent', $now, 200, 'application/json', $hash, $relativePath, 'x-search-v1', 'parsed', $now]);
    $rawId = (int) $pdo->lastInsertId();

    $candidateStmt = $pdo->prepare('INSERT INTO discovery_candidates (source_id, external_key, post_url, author_handle, text, posted_at, engagement_json, raw_record_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(source_id, external_key) DO UPDATE SET post_url = excluded.post_url, author_handle = excluded.author_handle, text = excluded.text, posted_at = excluded.posted_at, engagement_json = excluded.engagement_json, raw_record_id = excluded.raw_record_id, updated_at = excluded.updated_at');
    foreach ($records as $record) {
        $candidateStmt->execute([$sourceId, $record['external_key'], $record['post_url'], $record['author_handle'], $record['text'], $record['posted_at'], json_encode($record['engagement'], JSON_UNESCAPED_SLASHES), $rawId, 'unreviewed', $now, $now]);
    }

    $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, fetched_count = ?, created_count = ?, updated_count = ? WHERE id = ?')->execute([$now, 'complete', count($records), count($records), 0, $runId]);
    $pdo->prepare('UPDATE sources SET last_success_at = ?, updated_at = ? WHERE id = ?')->execute([$now, $now, $sourceId]);
    fwrite(STDOUT, 'Discovered ' . count($records) . " X posts. They remain unreviewed candidates.\n");
} catch (Throwable $error) {
    $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, error_count = 1 WHERE id = ?')->execute([gmdate('c'), 'failed', $runId]);
    $pdo->prepare('UPDATE sources SET last_error_at = ?, updated_at = ? WHERE id = ?')->execute([gmdate('c'), gmdate('c'), $sourceId]);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
