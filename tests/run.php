<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap.php';

use App\Repositories\DiscoveryCandidateRepository;
use App\Repositories\HackathonRepository;
use App\Services\CandidateReviewService;
use App\Services\SorsaPromotionService;
use App\Services\VerificationService;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $file) {
    foreach (preg_split('/;\s*(?:\r?\n|$)/', (string) file_get_contents($file)) ?: [] as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if (!$condition) {
        $failed++;
        fwrite(STDERR, "FAIL: {$message}\n");
        return;
    }
    $passed++;
    fwrite(STDOUT, "PASS: {$message}\n");
};

$now = gmdate('c');
$source = $pdo->prepare('INSERT INTO sources (source_key, name, kind, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
$source->execute(['test', 'Test source', 'test', $now, $now]);
$sourceId = (int) $pdo->lastInsertId();

$candidate = $pdo->prepare('INSERT INTO discovery_candidates (source_id, external_key, post_url, text, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
$candidate->execute([$sourceId, 'lead-1', 'https://x.com/i/web/status/lead-1', 'A real event lead', 'unreviewed', $now, $now]);
$candidateId = (int) $pdo->lastInsertId();

$candidates = new DiscoveryCandidateRepository($pdo);
$review = new CandidateReviewService($candidates);
$hackathonId = $review->convert($candidateId, [
    'title' => 'Verified test event',
    'official_url' => 'https://example.com/events/test',
    'review_note' => 'Manual test conversion',
]);
$converted = $candidates->find($candidateId);
$created = $pdo->prepare('SELECT verification_status FROM hackathons WHERE id = ?');
$created->execute([$hackathonId]);
$assert($converted['status'] === 'converted', 'candidate conversion marks the candidate converted');
$assert($created->fetchColumn() === 'unreviewed', 'candidate conversion never auto-verifies an event');
$assert((int) $pdo->query("SELECT COUNT(*) FROM hackathon_links WHERE hackathon_id = {$hackathonId} AND kind = 'discovery'")->fetchColumn() === 1, 'candidate provenance is linked to the hackathon');

$pdo->prepare("INSERT INTO hackathons (source_id, canonical_url, official_url, title, status, verification_status, end_at_utc, registration_deadline_utc, prize_text, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'verified', ?, ?, ?, ?, ?)")->execute([$sourceId, 'https://example.com/events/live', 'https://example.com/events/live', 'Live event', 'active', gmdate('c', time() + 3600), gmdate('c', time() + 1800), '$1,000', $now, $now]);
$unreviewed = new HackathonRepository($pdo);
$listings = $unreviewed->search([]);
$assert(count($listings) === 1 && $listings[0]['title'] === 'Live event', 'public search excludes unreviewed events');
$assert($listings[0]['status'] === 'active', 'status refresh keeps active events active');

$past = gmdate('c', time() - 3600);
$pdo->prepare("INSERT INTO hackathons (source_id, canonical_url, official_url, title, status, verification_status, end_at_utc, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', 'verified', ?, ?, ?)")->execute([$sourceId, 'https://example.com/events/past', 'https://example.com/events/past', 'Past event', $past, $now, $now]);
$listings = $unreviewed->search([]);
$pastStatus = $pdo->query("SELECT status FROM hackathons WHERE canonical_url = 'https://example.com/events/past'")->fetchColumn();
$assert($pastStatus === 'closed', 'status refresh closes ended events');

$invalidCaught = false;
try {
    $review->convert($candidateId, ['title' => 'Duplicate review', 'official_url' => 'https://example.com/duplicate']);
} catch (RuntimeException) {
    $invalidCaught = true;
}
$assert($invalidCaught, 'review rejects candidates that were already converted');

$invalidHackathon = $pdo->prepare("INSERT INTO hackathons (source_id, canonical_url, official_url, title, status, verification_status, created_at, updated_at) VALUES (?, ?, ?, ?, 'unknown', 'unreviewed', ?, ?)");
$invalidHackathon->execute([$sourceId, 'https://127.0.0.1/private', 'https://127.0.0.1/private', 'Unsafe URL', $now, $now]);
$verification = new VerificationService($pdo);
$result = $verification->verify((int) $pdo->lastInsertId(), 'https://127.0.0.1/private');
$assert($result === 'rejected', 'verification rejects private-network official URLs');

$sorsaSource = $pdo->prepare('INSERT INTO sources (source_key, name, kind, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
$sorsaSource->execute(['sorsa-search', 'Sorsa X search', 'social-api', $now, $now]);
$sorsaSourceId = (int) $pdo->lastInsertId();
$promotionCandidate = $pdo->prepare('INSERT INTO discovery_candidates (source_id, external_key, post_url, text, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
$promotionCandidate->execute([$sorsaSourceId, 'sorsa-no-link', 'https://x.com/i/web/status/none', 'A hackathon announcement without an official registration link.', 'unreviewed', $now, $now]);
$promotionRepository = new DiscoveryCandidateRepository($pdo);
$promotion = new SorsaPromotionService($promotionRepository, new CandidateReviewService($promotionRepository), new VerificationService($pdo));
$assert($promotion->promote() === 0, 'Sorsa promotion skips leads without explicit official links');
$promotionStatus = $pdo->query("SELECT status FROM discovery_candidates WHERE external_key = 'sorsa-no-link'")->fetchColumn();
$assert($promotionStatus === 'unreviewed', 'skipped Sorsa leads remain unreviewed');

fwrite(STDOUT, "{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
