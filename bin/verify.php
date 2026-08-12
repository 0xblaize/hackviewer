<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Database;

$pdo = Database::connection();
$rows = $pdo->query("SELECT id, official_url FROM hackathons WHERE verification_status = 'unreviewed'")->fetchAll();
foreach ($rows as $row) {
    $urlOk = filter_var($row['official_url'], FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($row['official_url']), 'https://');
    $status = $urlOk ? 'verified' : 'rejected';
    $pdo->prepare('UPDATE hackathons SET verification_status = ?, last_verified_at = ?, updated_at = ? WHERE id = ?')->execute([$status, gmdate('c'), gmdate('c'), $row['id']]);
    $pdo->prepare('INSERT INTO verification_checks (hackathon_id, check_type, result, evidence_url, evidence_excerpt, checked_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$row['id'], 'official_url', $urlOk ? 'pass' : 'fail', $row['official_url'], $urlOk ? 'HTTPS official URL format check passed.' : 'URL did not pass HTTPS validation.', gmdate('c')]);
}
fwrite(STDOUT, 'Checked ' . count($rows) . " listings.\n");
