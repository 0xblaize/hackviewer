<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DiscoveryCandidateRepository;

final class SorsaPromotionService
{
    public function __construct(
        private readonly DiscoveryCandidateRepository $candidates,
        private readonly CandidateReviewService $review,
        private readonly VerificationService $verification,
    ) {
    }

    public function promote(int $limit = 100): int
    {
        $this->cleanupIncompletePromotions();
        $promoted = 0;
        foreach ($this->candidates->sorsaPromotionQueue($limit) as $candidate) {
            $facts = $this->extractFacts((string) $candidate['text']);
            if ($facts === null) {
                continue;
            }
            $inspection = $this->findVerifiedPage((string) $candidate['text']);
            if ($inspection === null) {
                continue;
            }

            $title = $inspection['title'] !== '' ? $inspection['title'] : $this->fallbackTitle((string) $candidate['text']);
            if ($title === '') {
                continue;
            }

            try {
                $hackathonId = $this->review->convert((int) $candidate['id'], [
                    'title' => $title,
                    'official_url' => $inspection['evidence_url'],
                    'platform_name' => 'Sorsa verified link',
                    'description' => trim((string) $candidate['text']),
                    'start_at_utc' => $facts['start_at_utc'],
                    'end_at_utc' => $facts['end_at_utc'],
                    'registration_deadline_utc' => $facts['registration_deadline_utc'],
                    'prize_text' => $facts['prize_text'],
                    'prize_amount_minor' => $facts['prize_amount_minor'],
                    'prize_currency' => $facts['prize_currency'],
                    'hackathon_type' => 'Hackathon',
                    'online_or_location' => $facts['online_or_location'],
                    'review_note' => 'Automatically promoted from a Sorsa lead after the linked official HTTPS page passed verification with future dates and prize details.',
                ]);
                $this->verification->recordInspection($hackathonId, $inspection);
                $promoted++;
            } catch (\Throwable) {
                continue;
            }
        }
        return $promoted;
    }

    /** @return array<string, mixed>|null */
    private function extractFacts(string $text): ?array
    {
        if (!preg_match('/\b(?:hackathon|buildathon)\b/i', $text)) {
            return null;
        }
        if (!preg_match('/(?:\$|€|£|₹)\s*[0-9][0-9,]*(?:\.\d{1,2})?\s*(?:k|m)?/i', $text, $prizeMatch)) {
            return null;
        }
        $prizeText = trim((string) $prizeMatch[0]);
        $currency = str_contains($prizeText, '€') ? 'EUR' : (str_contains($prizeText, '£') ? 'GBP' : (str_contains($prizeText, '₹') ? 'INR' : 'USD'));
        $numeric = preg_replace('/[^0-9.]/', '', strtolower($prizeText));
        $amount = (float) $numeric;
        if (str_contains(strtolower($prizeText), 'm')) {
            $amount *= 1000000;
        } elseif (str_contains(strtolower($prizeText), 'k')) {
            $amount *= 1000;
        }
        $dates = $this->extractDates($text);
        if ($dates === null || strtotime($dates['end_at_utc']) <= time()) {
            return null;
        }
        return [
            'start_at_utc' => $dates['start_at_utc'],
            'end_at_utc' => $dates['end_at_utc'],
            'registration_deadline_utc' => $dates['end_at_utc'],
            'prize_text' => $prizeText,
            'prize_amount_minor' => (int) round($amount * 100),
            'prize_currency' => $currency,
            'online_or_location' => preg_match('/\bonline|virtual|remote\b/i', $text) ? 'Online' : null,
        ];
    }

    /** @return array{start_at_utc: string, end_at_utc: string}|null */
    private function extractDates(string $text): ?array
    {
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December';
        if (!preg_match_all('/\b(' . $months . '|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\.?\s+(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?(?:,?\s*(20\d{2}))?/i', $text, $matches, PREG_SET_ORDER)) {
            return null;
        }
        $dates = [];
        foreach ($matches as $match) {
            $year = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : (int) gmdate('Y');
            $month = date('m', strtotime($match[1] . ' 1 ' . $year));
            if ($month === false) {
                continue;
            }
            $start = strtotime(sprintf('%04d-%s-%02d 23:59:59 UTC', $year, $month, (int) $match[2]));
            $endDay = ($match[3] ?? '') !== '' ? (int) $match[3] : (int) $match[2];
            $end = strtotime(sprintf('%04d-%s-%02d 23:59:59 UTC', $year, $month, $endDay));
            if ($start === false || $end === false || $end < $start) {
                continue;
            }
            if ($end <= time() && (!isset($match[4]) || $match[4] === '')) {
                $year++;
                $start = strtotime(sprintf('%04d-%s-%02d 23:59:59 UTC', $year, $month, (int) $match[2]));
                $end = strtotime(sprintf('%04d-%s-%02d 23:59:59 UTC', $year, $month, $endDay));
            }
            $dates[] = ['start' => $start, 'end' => $end];
        }
        if ($dates === []) {
            return null;
        }
        usort($dates, static fn (array $a, array $b): int => $a['end'] <=> $b['end']);
        $first = $dates[0];
        $last = $dates[count($dates) - 1];
        return ['start_at_utc' => gmdate('c', $first['start']), 'end_at_utc' => gmdate('c', $last['end'])];
    }

    private function cleanupIncompletePromotions(): void
    {
        $this->candidates->connection()->beginTransaction();
        try {
            $rows = $this->candidates->connection()->query("SELECT h.id, c.id AS candidate_id FROM hackathons h INNER JOIN discovery_candidates c ON c.converted_hackathon_id = h.id INNER JOIN sources s ON s.id = c.source_id WHERE s.source_key = 'sorsa-search' AND h.platform_name = 'Sorsa verified link' AND (h.end_at_utc IS NULL OR h.registration_deadline_utc IS NULL OR h.prize_text IS NULL OR h.prize_text = '')")->fetchAll();
            foreach ($rows as $row) {
                $this->candidates->connection()->prepare("UPDATE discovery_candidates SET status = 'unreviewed', converted_hackathon_id = NULL, reviewed_at = NULL, review_note = NULL, updated_at = ? WHERE id = ?")->execute([gmdate('c'), $row['candidate_id']]);
                $this->candidates->connection()->prepare('DELETE FROM hackathon_links WHERE hackathon_id = ?')->execute([$row['id']]);
                $this->candidates->connection()->prepare('DELETE FROM verification_checks WHERE hackathon_id = ?')->execute([$row['id']]);
                $this->candidates->connection()->prepare('DELETE FROM hackathons WHERE id = ?')->execute([$row['id']]);
            }
            $this->candidates->connection()->commit();
        } catch (\Throwable $error) {
            if ($this->candidates->connection()->inTransaction()) {
                $this->candidates->connection()->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{status: string, check_result: string, evidence_url: string, excerpt: string, title: string}|null */
    private function findVerifiedPage(string $text): ?array
    {
        if (!preg_match('/\b(?:hackathon|buildathon|competition|challenge)\b/i', $text)) {
            return null;
        }
        if (!preg_match_all('~https?://[^\s<>"\')]+~i', $text, $matches)) {
            return null;
        }
        foreach ($matches[0] as $rawUrl) {
            $url = rtrim($rawUrl, '.,;:!?)]}');
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || $this->isSocialHost($host)) {
                continue;
            }
            $inspection = $this->verification->preflight($url);
            if ($inspection['status'] === 'verified' && $this->looksLikeHackathon($inspection['title'])) {
                return $inspection;
            }
        }
        return null;
    }

    private function isSocialHost(string $host): bool
    {
        return $host === 'x.com'
            || str_ends_with($host, '.x.com')
            || $host === 'twitter.com'
            || str_ends_with($host, '.twitter.com')
            || $host === 't.co'
            || str_ends_with($host, '.t.co')
            || str_contains($host, 'twimg.com');
    }

    private function looksLikeHackathon(string $title): bool
    {
        return $title !== '';
    }

    private function fallbackTitle(string $text): string
    {
        $line = preg_split('/\R/', trim($text))[0] ?? '';
        $line = trim((string) preg_replace('/https?://\S+/i', '', $line));
        return trim((string) preg_replace('/\s+/', ' ', $line));
    }
}
