<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;

$pdo = Database::connection();
$removed = $pdo->exec("DELETE FROM raw_ingestion_records WHERE created_at < datetime('now', '-30 days')");
fwrite(STDOUT, "Removed {$removed} old raw ingestion records.\n");
