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
        $now = gmdate('c');
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !str_starts_with(strtolower($url), 'https://')) {
            $this->check($id, 'official_page', 'fail', $url, 'The official URL must be a valid HTTPS URL.', $now);
            $this->setStatus($id, 'rejected', null);
            return 'rejected';
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
        $status = $this->responseStatus($http_response_header ?? []);
        $contentType = $this->contentType($http_response_header ?? []);
        if ($status >= 300 && $status < 400) {
            $location = $this->headerValue($http_response_header ?? [], 'location');
            if ($location === '' || !str_starts_with(strtolower($location), 'https://')) {
                $this->check($id, 'official_page', 'fail', $url, 'Redirect did not remain on HTTPS.', $now);
                $this->setStatus($id, 'rejected', null);
                return 'rejected';
            }
            $this->check($id, 'official_page', 'fail', $location, 'Redirect requires a later verification pass at the final HTTPS URL.', $now);
            return 'unreviewed';
        }
        if ($status >= 500 || $status === 0 || $body === false) {
            $this->check($id, 'official_page', 'retry', $url, "Temporary fetch failure (HTTP {$status}).", $now);
            return 'unreviewed';
        }
        if ($status < 200 || $status >= 400) {
            $this->check($id, 'official_page', 'fail', $url, "Official page returned HTTP {$status}.", $now);
            $this->setStatus($id, 'rejected', null);
            return 'rejected';
        }
        if ($contentType !== '' && !str_contains(strtolower($contentType), 'html')) {
            $this->check($id, 'official_page', 'fail', $url, "Official URL returned non-HTML content type: {$contentType}.", $now);
            $this->setStatus($id, 'rejected', null);
            return 'rejected';
        }
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', (string) $body, $matches)) {
            $title = trim(preg_replace('/\s+/', ' ', strip_tags($matches[1])) ?? '');
        }
        $excerpt = "HTTP {$status}; content type " . ($contentType ?: 'not reported') . ($title !== '' ? "; page title: {$title}" : '');
        $this->check($id, 'official_page', 'pass', $url, $excerpt, $now);
        $this->setStatus($id, 'verified', $now);
        return 'verified';
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
