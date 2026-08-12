<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Sources\SorsaSearchAdapter;

$apiKey = trim((string) config('sorsa_api_key', ''));
$query = $argv[1] ?? trim((string) config('sorsa_search_query', 'hackathon'));
$endpoint = trim((string) config('sorsa_search_endpoint_url', 'https://api.sorsa.io/v3/search-tweets'));
$queryField = trim((string) config('sorsa_search_query_field', 'query'));

if ($apiKey === '') {
    fwrite(STDERR, "Set SORSA_API_KEY in .env before running this command.\n");
    exit(1);
}
if ($query === '') {
    fwrite(STDERR, "Provide a keyword, for example: php bin/search-sorsa.php hackathon\n");
    exit(1);
}

$pdo = Database::connection();
$now = gmdate('c');
$source = $pdo->prepare('INSERT INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(source_key) DO UPDATE SET updated_at = excluded.updated_at');
$source->execute(['sorsa-search', 'Sorsa X search', 'social-api', $endpoint, $now, $now]);
$sourceId = (int) $pdo->query("SELECT id FROM sources WHERE source_key = 'sorsa-search'")->fetchColumn();

$run = $pdo->prepare('INSERT INTO ingestion_runs (source_id, started_at, status) VALUES (?, ?, ?)');
$run->execute([$sourceId, $now, 'running']);
$runId = (int) $pdo->lastInsertId();

try {
    $adapter = new SorsaSearchAdapter($apiKey, $query, $endpoint, $queryField);
    $records = iterator_to_array($adapter->fetch());
    $raw = json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($raw === false) {
        throw new RuntimeException('Unable to encode Sorsa records.');
    }
    $hash = hash('sha256', $raw);
    $relativePath = 'sorsa-search/' . gmdate('Ymd-His') . '-' . $hash . '.json';
    $absolutePath = appRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'raw' . DIRECTORY_SEPARATOR . $relativePath;
    if (!is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0775, true);
    }
    file_put_contents($absolutePath, $raw, LOCK_EX);

    $rawStmt = $pdo->prepare('INSERT INTO raw_ingestion_records (source_id, external_key, request_url, retrieved_at, http_status, content_type, content_hash, payload_path, parser_version, parse_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $rawStmt->execute([$sourceId, $hash, $endpoint, $now, 200, 'application/json', $hash, $relativePath, 'sorsa-search-v1', 'parsed', $now]);
    $rawId = (int) $pdo->lastInsertId();

    $candidateStmt = $pdo->prepare('INSERT INTO discovery_candidates (source_id, external_key, post_url, author_handle, text, posted_at, engagement_json, raw_record_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(source_id, external_key) DO UPDATE SET post_url = excluded.post_url, author_handle = excluded.author_handle, text = excluded.text, posted_at = excluded.posted_at, engagement_json = excluded.engagement_json, raw_record_id = excluded.raw_record_id, updated_at = excluded.updated_at');
    foreach ($records as $record) {
        $candidateStmt->execute([$sourceId, $record['external_key'], $record['post_url'], $record['author_handle'], $record['text'], $record['posted_at'], json_encode($record['engagement'], JSON_UNESCAPED_SLASHES), $rawId, 'unreviewed', $now, $now]);
    }

    $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, fetched_count = ?, created_count = ?, updated_count = ? WHERE id = ?')->execute([$now, 'complete', count($records), count($records), 0, $runId]);
    $pdo->prepare('UPDATE sources SET last_success_at = ?, updated_at = ? WHERE id = ?')->execute([$now, $now, $sourceId]);
    fwrite(STDOUT, 'Found ' . count($records) . " Sorsa posts for '{$query}'. They remain unreviewed candidates.\n");
} catch (Throwable $error) {
    $pdo->prepare('UPDATE ingestion_runs SET finished_at = ?, status = ?, error_count = 1 WHERE id = ?')->execute([gmdate('c'), 'failed', $runId]);
    $pdo->prepare('UPDATE sources SET last_error_at = ?, updated_at = ? WHERE id = ?')->execute([gmdate('c'), gmdate('c'), $sourceId]);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
