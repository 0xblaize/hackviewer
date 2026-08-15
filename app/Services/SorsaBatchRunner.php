<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Sources\SorsaSearchAdapter;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class SorsaBatchRunner
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function run(string $batchDate, array $queries, bool $force = false): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $batchDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $batchDate) {
            throw new RuntimeException('Batch date must be a valid calendar date in YYYY-MM-DD format.');
        }
        $normalizedQueries = [];
        foreach ($queries as $query) {
            if (!is_string($query)) {
                continue;
            }
            $query = trim($query);
            if ($query !== '') {
                $normalizedQueries[] = $query;
            }
        }
        $queries = array_values(array_unique($normalizedQueries));
        if ($queries === []) {
            throw new RuntimeException('SORSA_BATCH_QUERIES must contain at least one query.');
        }
        if (count($queries) > 20) {
            throw new RuntimeException('SORSA_BATCH_QUERIES cannot contain more than 20 queries.');
        }

        $skipOrdinals = [];
        $baseTotals = ['fetched_count' => 0, 'created_count' => 0, 'updated_count' => 0, 'duplicate_count' => 0];
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $existing = $this->pdo->prepare('SELECT * FROM sorsa_batches WHERE batch_date = ?');
            $existing->execute([$batchDate]);
            $batch = $existing->fetch();
            if ($batch !== false) {
                if ($batch['status'] === 'complete') {
                    $this->pdo->commit();
                    return ['status' => 'already-complete', 'batch_date' => $batchDate, 'fetched_count' => (int) $batch['fetched_count']];
                }
                if (!$force) {
                    $this->pdo->rollBack();
                    throw new RuntimeException($batch['status'] === 'running' ? 'A Sorsa batch is already running for this date; use --force only to recover a stale run.' : 'This Sorsa batch is incomplete; use --force to retry it.');
                }
                $batchId = (int) $batch['id'];
                if ($batch['status'] !== 'running') {
                    $completed = $this->pdo->prepare("SELECT query_ordinal FROM sorsa_batch_queries WHERE batch_id = ? AND status = 'complete'");
                    $completed->execute([$batchId]);
                    foreach ($completed->fetchAll(PDO::FETCH_COLUMN) as $ordinal) {
                        $skipOrdinals[(int) $ordinal] = true;
                    }
                    $baseTotals = [
                        'fetched_count' => (int) $batch['fetched_count'],
                        'created_count' => (int) $batch['created_count'],
                        'updated_count' => (int) $batch['updated_count'],
                        'duplicate_count' => (int) $batch['duplicate_count'],
                    ];
                    $this->pdo->prepare("DELETE FROM sorsa_batch_queries WHERE batch_id = ? AND status != 'complete'")->execute([$batchId]);
                } else {
                    $this->pdo->prepare('DELETE FROM sorsa_batch_queries WHERE batch_id = ?')->execute([$batchId]);
                }
                $this->pdo->prepare('UPDATE sorsa_batches SET status = ?, started_at = ?, finished_at = NULL, error_count = 0, error_message = NULL WHERE id = ?')->execute(['running', gmdate('c'), $batchId]);
            } else {
                $insert = $this->pdo->prepare('INSERT INTO sorsa_batches (batch_date, status, started_at) VALUES (?, ?, ?)');
                $insert->execute([$batchDate, 'running', gmdate('c')]);
                $batchId = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        $apiKey = trim((string) config('sorsa_api_key', ''));
        $endpoint = trim((string) config('sorsa_search_endpoint_url', 'https://api.sorsa.io/v3/search-tweets'));
        $queryField = trim((string) config('sorsa_search_query_field', 'query'));
        if ($apiKey === '') {
            $this->finish($batchId, 'failed', ['error_count' => 1, 'error_message' => 'SORSA_API_KEY is not configured.']);
            throw new RuntimeException('SORSA_API_KEY is not configured.');
        }

        $sourceId = $this->sourceId($endpoint);
        $totals = $baseTotals + ['error_count' => 0];
        $batchSeen = [];
        foreach ($queries as $ordinal => $query) {
            if (isset($skipOrdinals[$ordinal + 1])) {
                continue;
            }
            $queryStarted = gmdate('c');
            $queryInsert = $this->pdo->prepare('INSERT INTO sorsa_batch_queries (batch_id, query_ordinal, query_text, status, started_at) VALUES (?, ?, ?, ?, ?)');
            $queryInsert->execute([$batchId, $ordinal + 1, $query, 'running', $queryStarted]);
            $queryId = (int) $this->pdo->lastInsertId();
            try {
                $adapter = new SorsaSearchAdapter($apiKey, $query, $endpoint, $queryField);
                $response = $adapter->fetchResponse();
                $records = $response['records'];
                $raw = $response['raw_body'];
                $hash = hash('sha256', $raw);
                $relativePath = 'sorsa-search/batches/' . $batchDate . '-' . ($ordinal + 1) . '-' . $hash . '.json';
                $absolutePath = appRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'raw' . DIRECTORY_SEPARATOR . $relativePath;
                if (!is_dir(dirname($absolutePath)) && !mkdir(dirname($absolutePath), 0775, true) && !is_dir(dirname($absolutePath))) {
                    throw new RuntimeException('Unable to create Sorsa raw payload directory.');
                }
                if (file_put_contents($absolutePath, $raw, LOCK_EX) === false) {
                    throw new RuntimeException('Unable to write Sorsa raw payload.');
                }
                $retrievedAt = gmdate('c');
                $rawStmt = $this->pdo->prepare('INSERT INTO raw_ingestion_records (source_id, external_key, request_url, retrieved_at, http_status, content_type, content_hash, payload_path, parser_version, parse_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $rawStmt->execute([$sourceId, $batchDate . ':' . ($ordinal + 1), $endpoint, $retrievedAt, $response['http_status'], $response['content_type'] !== '' ? $response['content_type'] : null, $hash, $relativePath, 'sorsa-search-v1', 'parsed', $retrievedAt]);
                $rawId = (int) $this->pdo->lastInsertId();
                $this->pdo->prepare('UPDATE sorsa_batch_queries SET raw_record_id = ? WHERE id = ?')->execute([$rawId, $queryId]);
                $seen = [];
                $newCount = 0;
                $updatedCount = 0;
                $duplicateCount = 0;
                foreach ($records as $record) {
                    $key = (string) ($record['external_key'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    if (isset($seen[$key]) || isset($batchSeen[$key])) {
                        $duplicateCount++;
                        continue;
                    }
                    $seen[$key] = true;
                    $batchSeen[$key] = true;
                    $exists = $this->pdo->prepare('SELECT id FROM discovery_candidates WHERE source_id = ? AND external_key = ?');
                    $exists->execute([$sourceId, $key]);
                    $wasExisting = $exists->fetchColumn() !== false;
                    $this->pdo->prepare('INSERT INTO discovery_candidates (source_id, external_key, post_url, author_handle, text, posted_at, engagement_json, raw_record_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(source_id, external_key) DO UPDATE SET post_url = excluded.post_url, author_handle = excluded.author_handle, text = excluded.text, posted_at = excluded.posted_at, engagement_json = excluded.engagement_json, raw_record_id = excluded.raw_record_id, updated_at = excluded.updated_at')->execute([$sourceId, $key, $record['post_url'], $record['author_handle'], $record['text'], $record['posted_at'], json_encode($record['engagement'], JSON_UNESCAPED_SLASHES), $rawId, 'unreviewed', gmdate('c'), gmdate('c')]);
                    $wasExisting ? $updatedCount++ : $newCount++;
                }
                $queryFinished = gmdate('c');
                $this->pdo->prepare('UPDATE sorsa_batch_queries SET status = ?, finished_at = ?, fetched_count = ?, created_count = ?, updated_count = ?, duplicate_count = ? WHERE id = ?')->execute(['complete', $queryFinished, count($records), $newCount, $updatedCount, $duplicateCount, $queryId]);
                $totals['fetched_count'] += count($records);
                $totals['created_count'] += $newCount;
                $totals['updated_count'] += $updatedCount;
                $totals['duplicate_count'] += $duplicateCount;
            } catch (Throwable $error) {
                $totals['error_count']++;
                $this->pdo->prepare('UPDATE sorsa_batch_queries SET status = ?, finished_at = ?, error_message = ? WHERE id = ?')->execute(['failed', gmdate('c'), $error->getMessage(), $queryId]);
            }
        }

        $status = $totals['error_count'] === 0 ? 'complete' : ($totals['fetched_count'] > 0 ? 'partial' : 'failed');
        $this->finish($batchId, $status, $totals);
        if ($status !== 'complete') {
            throw new RuntimeException("Sorsa batch {$status}. Use --force to retry.");
        }
        return ['status' => $status, 'batch_date' => $batchDate] + $totals;
    }

    private function sourceId(string $endpoint): int
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(source_key) DO UPDATE SET updated_at = excluded.updated_at');
        $stmt->execute(['sorsa-search', 'Sorsa X search', 'social-api', $endpoint, $now, $now]);
        $id = $this->pdo->query("SELECT id FROM sources WHERE source_key = 'sorsa-search'")->fetchColumn();
        return (int) $id;
    }

    private function finish(int $batchId, string $status, array $totals): void
    {
        $this->pdo->prepare('UPDATE sorsa_batches SET status = ?, finished_at = ?, fetched_count = ?, created_count = ?, updated_count = ?, duplicate_count = ?, error_count = ?, error_message = ? WHERE id = ?')->execute([$status, gmdate('c'), $totals['fetched_count'] ?? 0, $totals['created_count'] ?? 0, $totals['updated_count'] ?? 0, $totals['duplicate_count'] ?? 0, $totals['error_count'] ?? 0, $totals['error_message'] ?? ($status === 'complete' ? null : 'One or more queries failed.'), $batchId]);
    }
}
