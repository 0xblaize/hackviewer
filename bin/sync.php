<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Services\DiscoveryPersister;
use App\Services\SorsaBatchRunner;
use App\Services\SorsaPromotionService;
use App\Services\VerificationService;
use App\Sources\JsonAdapter;
use App\Sources\RssAdapter;
use App\Sources\SourceRegistry;

$force = in_array('--force', array_slice($argv, 1), true);
$maxAge = 86400;
$pdo = Database::connection();
$now = time();
$persister = new DiscoveryPersister($pdo);
$failed = 0;
$updated = 0;
$refreshed = false;

function stale(?string $timestamp, int $now, int $maxAge): bool
{
    if ($timestamp === null || trim($timestamp) === '') {
        return true;
    }
    $time = strtotime($timestamp);
    return $time === false || ($now - $time) >= $maxAge;
}

$sources = SourceRegistry::all();
foreach ($sources as $key => $source) {
    if ($source['kind'] === 'discovery') {
        continue;
    }
    if ($source['status'] === 'invalid-endpoint') {
        $failed++;
        fwrite(STDERR, "{$key}: endpoint must use HTTPS.\n");
        continue;
    }
    if (!$source['configured']) {
        continue;
    }
    $sourceStmt = $pdo->prepare('SELECT last_success_at FROM sources WHERE source_key = ? LIMIT 1');
    $sourceStmt->execute([$key]);
    $lastSuccess = $sourceStmt->fetchColumn();
    if (!$force && !stale($lastSuccess === false ? null : (string) $lastSuccess, $now, $maxAge)) {
        fwrite(STDOUT, "Skipped {$key}: source data is less than 24 hours old.\n");
        continue;
    }
    try {
        $endpoint = (string) $source['endpoint'];
        $path = strtolower((string) (parse_url($endpoint, PHP_URL_PATH) ?? ''));
        $adapter = str_ends_with($path, '.xml')
            ? new RssAdapter($key, $endpoint)
            : new JsonAdapter($key, $endpoint, array_merge(['items_path' => ''], (array) ($source['request'] ?? [])));
        $count = $persister->ingest($key, (string) $source['name'], (string) $source['kind'], $endpoint, $adapter->fetch());
        $updated += $count;
        $refreshed = true;
        fwrite(STDOUT, "{$key}: ingested {$count} records.\n");
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "{$key}: {$error->getMessage()}\n");
    }
}

$apiKey = trim((string) config('sorsa_api_key', ''));
if ($apiKey !== '') {
    $batchDate = gmdate('Y-m-d');
    $batchStmt = $pdo->prepare('SELECT finished_at, status FROM sorsa_batches WHERE batch_date = ? LIMIT 1');
    $batchStmt->execute([$batchDate]);
    $batch = $batchStmt->fetch();
    $sorsaFresh = $batch && $batch['status'] === 'complete' && !stale($batch['finished_at'] ?? null, $now, $maxAge);
    if ($force || !$sorsaFresh) {
        try {
            $result = (new SorsaBatchRunner($pdo))->run($batchDate, config('sorsa_batch_queries', []), $force);
            $updated += (int) ($result['created_count'] ?? 0) + (int) ($result['updated_count'] ?? 0);
            $refreshed = true;
            fwrite(STDOUT, "sorsa: batch {$result['status']}.\n");
        } catch (Throwable $error) {
            $failed++;
            fwrite(STDERR, "sorsa: {$error->getMessage()}\n");
        }
    } else {
        fwrite(STDOUT, "Skipped sorsa: source data is less than 24 hours old.\n");
    }
} else {
    fwrite(STDOUT, "Skipped sorsa: SORSA_API_KEY is not configured.\n");
}

if ($refreshed || $force) {
    $candidateRepository = new App\Repositories\DiscoveryCandidateRepository($pdo);
    $promotionCount = (new SorsaPromotionService(
        $candidateRepository,
        new App\Services\CandidateReviewService($candidateRepository),
        new VerificationService($pdo)
    ))->promote();
    if ($promotionCount > 0) {
        $updated += $promotionCount;
        fwrite(STDOUT, "sorsa: promoted {$promotionCount} verified opportunities.\n");
    }
    $verification = new VerificationService($pdo);
    $verified = 0;
    $rejected = 0;
    $unreviewed = 0;
    foreach ($pdo->query("SELECT id, official_url FROM hackathons WHERE verification_status = 'unreviewed'")->fetchAll() as $row) {
        try {
            $status = $verification->verify((int) $row['id'], (string) $row['official_url']);
            ${$status}++;
        } catch (Throwable $error) {
            $failed++;
            $unreviewed++;
            fwrite(STDERR, "verification #{$row['id']}: {$error->getMessage()}\n");
        }
    }
    fwrite(STDOUT, "Verification complete: {$verified} verified, {$rejected} rejected, {$unreviewed} pending.\n");
}

fwrite(STDOUT, "Source sync complete: {$updated} records refreshed.\n");
exit($failed > 0 ? 1 : 0);
