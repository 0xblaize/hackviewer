<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class DiscoveryCandidateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function all(string $status = 'unreviewed'): array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, s.name AS source_name FROM discovery_candidates c LEFT JOIN sources s ON s.id = c.source_id WHERE c.status = :status ORDER BY c.posted_at DESC, c.updated_at DESC LIMIT 100');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function publicLeads(int $limit = 30): array
    {
        $limit = max(1, min($limit, 50));
        $stmt = $this->pdo->query('SELECT c.id, c.source_id, c.external_key, c.post_url, c.author_handle, c.text, c.posted_at, c.status, s.name AS source_name FROM discovery_candidates c LEFT JOIN sources s ON s.id = c.source_id WHERE c.status = \'unreviewed\' ORDER BY c.posted_at DESC, c.updated_at DESC LIMIT ' . $limit);
        return $stmt->fetchAll();
    }

    public function sorsaPromotionQueue(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        $stmt = $this->pdo->query("SELECT c.*, s.name AS source_name FROM discovery_candidates c INNER JOIN sources s ON s.id = c.source_id WHERE c.status = 'unreviewed' AND s.source_key = 'sorsa-search' ORDER BY c.posted_at DESC, c.updated_at DESC LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, s.name AS source_name, r.payload_path, r.retrieved_at FROM discovery_candidates c LEFT JOIN sources s ON s.id = c.source_id LEFT JOIN raw_ingestion_records r ON r.id = c.raw_record_id WHERE c.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function reject(int $id, string $note = ''): void
    {
        $stmt = $this->pdo->prepare("UPDATE discovery_candidates SET status = 'rejected', reviewed_at = ?, review_note = ?, updated_at = ? WHERE id = ? AND status = 'unreviewed'");
        $now = gmdate('c');
        $stmt->execute([$now, $note !== '' ? $note : null, $now, $id]);
    }

    public function markConverted(int $id, int $hackathonId, string $note = ''): void
    {
        $stmt = $this->pdo->prepare("UPDATE discovery_candidates SET status = 'converted', converted_hackathon_id = ?, reviewed_at = ?, review_note = ?, updated_at = ? WHERE id = ? AND status = 'unreviewed'");
        $now = gmdate('c');
        $stmt->execute([$hackathonId, $now, $note !== '' ? $note : null, $now, $id]);
    }

    public function connection(): PDO
    {
        return $this->pdo;
    }
}
