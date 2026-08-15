<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;
use App\Services\VerificationService;

$pdo = Database::connection();
$service = new VerificationService($pdo);
$rows = $pdo->query("SELECT id, official_url FROM hackathons WHERE verification_status = 'unreviewed'")->fetchAll();
$counts = ['verified' => 0, 'rejected' => 0, 'unreviewed' => 0];
foreach ($rows as $row) {
    $status = $service->verify((int) $row['id'], (string) $row['official_url']);
    $counts[$status]++;
}
fwrite(STDOUT, 'Checked ' . count($rows) . ' listings: ' . json_encode($counts) . "\n");
