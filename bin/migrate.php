<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;

$pdo = Database::connection();
$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    $pdo->exec((string) file_get_contents($file));
}
$now = gmdate('c');
$stmt = $pdo->prepare('INSERT OR IGNORE INTO sources (source_key, name, kind, base_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['manual', 'Verified official links', 'manual', null, $now, $now]);

fwrite(STDOUT, "Database ready at " . config('database') . PHP_EOL);
