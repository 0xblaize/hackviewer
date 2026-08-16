<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DiscoveryCandidateRepository;
use App\Support\UrlNormalizer;
use PDO;
use RuntimeException;

final class CandidateReviewService
{
    public function __construct(private readonly DiscoveryCandidateRepository $candidates)
    {
    }

    public function reject(int $candidateId, string $note = ''): void
    {
        $candidate = $this->requireUnreviewed($candidateId);
        $this->candidates->reject((int) $candidate['id'], $note);
    }

    public function convert(int $candidateId, array $input): int
    {
        $candidate = $this->requireUnreviewed($candidateId);
        $title = trim((string) ($input['title'] ?? $candidate['text']));
        $officialUrl = trim((string) ($input['official_url'] ?? ''));
        if ($title === '' || filter_var($officialUrl, FILTER_VALIDATE_URL) === false || !str_starts_with(strtolower($officialUrl), 'https://')) {
            throw new RuntimeException('A title and HTTPS official URL are required.');
        }

        $canonicalKey = UrlNormalizer::normalize($officialUrl);
        $pdo = $this->candidates->connection();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT id FROM hackathons WHERE canonical_key = ? OR canonical_url = ? OR official_url = ? ORDER BY CASE WHEN verification_status = \'verified\' THEN 0 ELSE 1 END, id LIMIT 1');
            $existing->execute([$canonicalKey, $canonicalKey, $officialUrl]);
            $hackathonId = $existing->fetchColumn();
            if ($hackathonId === false) {
                foreach ($pdo->query('SELECT id, canonical_key, canonical_url, official_url FROM hackathons')->fetchAll() as $row) {
                    $rowKey = UrlNormalizer::normalize((string) ($row['canonical_key'] ?: $row['canonical_url'] ?: $row['official_url']));
                    if ($rowKey === $canonicalKey) {
                        $hackathonId = $row['id'];
                        break;
                    }
                }
            }
            $now = gmdate('c');
            if ($hackathonId === false) {
                $sourceId = (int) $candidate['source_id'];
                $end = $this->dateValue($input['end_at_utc'] ?? null);
                $status = $end !== null && strtotime($end) < time() ? 'closed' : 'upcoming';
                $stmt = $pdo->prepare('INSERT INTO hackathons (source_id, source_event_id, canonical_url, official_url, canonical_key, title, organizer_name, platform_name, description, hackathon_type, start_at_utc, end_at_utc, registration_deadline_utc, prize_amount_minor, prize_currency, prize_text, online_or_location, location_text, status, verification_status, what_to_know, last_seen_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$sourceId, 'candidate:' . $candidate['id'], $canonicalKey, $officialUrl, $canonicalKey, $title, $input['organizer_name'] ?? null, $input['platform_name'] ?? ($candidate['source_name'] ?? null), $input['description'] ?? null, $input['hackathon_type'] ?? null, $this->dateValue($input['start_at_utc'] ?? null), $end, $this->dateValue($input['registration_deadline_utc'] ?? null), $input['prize_amount_minor'] ?? null, $input['prize_currency'] ?? null, $input['prize_text'] ?? null, $input['online_or_location'] ?? null, $input['location_text'] ?? null, $status, 'unreviewed', $input['what_to_know'] ?? 'Review the official rules, eligibility, judging, prize terms, and submission requirements before signing up.', $now, $now, $now]);
                $hackathonId = (int) $pdo->lastInsertId();
            } else {
                $hackathonId = (int) $hackathonId;
            }
            $link = $pdo->prepare('INSERT INTO hackathon_links (hackathon_id, kind, url, label, is_primary, created_at) SELECT ?, ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM hackathon_links WHERE hackathon_id = ? AND url = ?)');
            $link->execute([$hackathonId, 'discovery', $candidate['post_url'], 'Discovery post', 0, $now, $hackathonId, $candidate['post_url']]);
            $this->candidates->markConverted($candidateId, $hackathonId, (string) ($input['review_note'] ?? ''));
            $pdo->commit();
            return $hackathonId;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function requireUnreviewed(int $id): array
    {
        $candidate = $this->candidates->find($id);
        if (!$candidate || $candidate['status'] !== 'unreviewed') {
            throw new RuntimeException('Candidate is missing or has already been reviewed.');
        }
        return $candidate;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('c', $timestamp);
    }
}
