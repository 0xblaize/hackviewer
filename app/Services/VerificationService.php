<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class VerificationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function verify(int $id, string $url): string
    {
        $inspection = $this->preflight($url);
        return $this->recordInspection($id, $inspection);
    }

    /** @param array{status: string, check_result: string, evidence_url: string, excerpt: string, title: string} $inspection */
    public function recordInspection(int $id, array $inspection): string
    {
        $now = gmdate('c');
        $this->check($id, 'official_page', $inspection['check_result'], $inspection['evidence_url'], $inspection['excerpt'], $now);
        if ($inspection['status'] === 'verified') {
            $this->setStatus($id, 'verified', $now);
        } elseif ($inspection['status'] === 'rejected') {
            $this->setStatus($id, 'rejected', null);
        }
        return $inspection['status'];
    }

    /** @return array{status: string, check_result: string, evidence_url: string, excerpt: string, title: string} */
    public function preflight(string $url): array
    {
        if (!$this->isSafeHttpsUrl($url)) {
            return ['status' => 'rejected', 'check_result' => 'fail', 'evidence_url' => $url, 'excerpt' => 'The official URL must be a public HTTPS URL without credentials or private-network destinations.', 'title' => ''];
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "Accept: text/html,application/xhtml+xml\r\nUser-Agent: Hackview/0.1 (+verification)\r\n",
        ]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = $this->responseStatus($headers);
        $contentType = $this->contentType($headers);
        if ($status >= 300 && $status < 400) {
            $location = $this->headerValue($headers, 'location');
            if ($location === '' || !$this->isSafeHttpsUrl($location)) {
                return ['status' => 'rejected', 'check_result' => 'fail', 'evidence_url' => $url, 'excerpt' => 'Redirect did not remain on a public HTTPS destination.', 'title' => ''];
            }
            return ['status' => 'unreviewed', 'check_result' => 'fail', 'evidence_url' => $location, 'excerpt' => 'Redirect requires a later verification pass at the final HTTPS URL.', 'title' => ''];
        }
        if ($status >= 500 || $status === 0 || $body === false) {
            return ['status' => 'unreviewed', 'check_result' => 'retry', 'evidence_url' => $url, 'excerpt' => "Temporary fetch failure (HTTP {$status}).", 'title' => ''];
        }
        if ($status < 200 || $status >= 400) {
            return ['status' => 'rejected', 'check_result' => 'fail', 'evidence_url' => $url, 'excerpt' => "Official page returned HTTP {$status}.", 'title' => ''];
        }
        if ($contentType !== '' && !str_contains(strtolower($contentType), 'html')) {
            return ['status' => 'rejected', 'check_result' => 'fail', 'evidence_url' => $url, 'excerpt' => "Official URL returned non-HTML content type: {$contentType}.", 'title' => ''];
        }
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', (string) $body, $matches)) {
            $title = trim(preg_replace('/\s+/', ' ', strip_tags($matches[1])) ?? '');
        }
        return ['status' => 'verified', 'check_result' => 'pass', 'evidence_url' => $url, 'excerpt' => "HTTP {$status}; content type " . ($contentType ?: 'not reported') . ($title !== '' ? "; page title: {$title}" : ''), 'title' => $title];
    }

    private function isSafeHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return false;
        }
        if (strcasecmp($host, 'localhost') === 0 || str_ends_with(strtolower($host), '.localhost') || str_ends_with(strtolower($host), '.local')) {
            return false;
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        return true;
    }

    private function check(int $id, string $type, string $result, ?string $url, string $excerpt, string $now): void
    {
        $this->pdo->prepare('INSERT INTO verification_checks (hackathon_id, check_type, result, evidence_url, evidence_excerpt, checked_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $type, $result, $url, $excerpt, $now]);
    }

    private function setStatus(int $id, string $status, ?string $verifiedAt): void
    {
        $this->pdo->prepare('UPDATE hackathons SET verification_status = ?, last_verified_at = ?, legitimacy_notes = ?, updated_at = ? WHERE id = ?')->execute([$status, $verifiedAt, $status === 'verified' ? 'Official HTTPS page fetched and inspected.' : null, gmdate('c'), $id]);
    }

    private function responseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    private function contentType(array $headers): string
    {
        return $this->headerValue($headers, 'content-type');
    }

    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($name) . ':')) {
                return trim(substr($header, strlen($name) + 1));
            }
        }
        return '';
    }
}
