<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Services\DiscoveryPersister;
use App\Sources\JsonAdapter;
use App\Sources\RssAdapter;
use App\Sources\SourceRegistry;

$sourceKey = null;
$list = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--list-sources') {
        $list = true;
    } elseif (str_starts_with($argument, '--source=')) {
        $sourceKey = substr($argument, 9);
    }
}

if ($list) {
    foreach (SourceRegistry::all() as $key => $source) {
        fwrite(STDOUT, sprintf("%-12s %-24s %s (%s)\n", $key, $source['name'], $source['status'], $source['endpoint_env']));
    }
    exit(0);
}

$sources = SourceRegistry::all();
if ($sourceKey !== null) {
    if (!isset($sources[$sourceKey])) {
        fwrite(STDERR, "Unknown source: {$sourceKey}\n");
        exit(1);
    }
    $sources = [$sourceKey => $sources[$sourceKey]];
}

$pdo = Database::connection();
$persister = new DiscoveryPersister($pdo);
$failed = 0;
foreach ($sources as $key => $source) {
    if ($source['kind'] === 'discovery') {
        fwrite(STDOUT, "Skipped {$key}: use its dedicated discovery command.\n");
        continue;
    }
    if (!$source['configured']) {
        fwrite(STDOUT, "Skipped {$key}: no permitted endpoint configured.\n");
        continue;
    }
    try {
        $endpoint = (string) $source['endpoint'];
        $adapter = str_ends_with(strtolower(parse_url($endpoint, PHP_URL_PATH) ?? ''), '.xml')
            ? new RssAdapter($key, $endpoint)
            : new JsonAdapter($key, $endpoint, ['items_path' => '']);
        $count = $persister->ingest($key, $source['name'], $source['kind'], $endpoint, $adapter->fetch());
        fwrite(STDOUT, "{$key}: ingested {$count} unreviewed records.\n");
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "{$key}: {$error->getMessage()}\n");
    }
}
exit($failed > 0 ? 1 : 0);
