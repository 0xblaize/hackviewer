<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class HackathonRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function search(array $filters): array
    {
        $this->refreshStatuses();
        $conditions = ["h.verification_status = 'verified'", "h.status IN ('active', 'upcoming')", "COALESCE(h.registration_deadline_utc, h.end_at_utc) IS NOT NULL", "datetime(COALESCE(h.registration_deadline_utc, h.end_at_utc)) > datetime('now')", "(h.prize_text IS NOT NULL AND h.prize_text != '') OR h.prize_amount_minor IS NOT NULL"];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(h.title LIKE :q OR h.organizer_name LIKE :q OR h.platform_name LIKE :q OR h.hackathon_type LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (($filters['status'] ?? '') !== '' && in_array($filters['status'], ['active', 'upcoming', 'closed'], true)) {
            $conditions[] = 'h.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['type'] ?? '') !== '') {
            $conditions[] = 'h.hackathon_type = :type';
            $params['type'] = $filters['type'];
        }
        if (($filters['source'] ?? '') !== '') {
            $conditions[] = 'h.platform_name = :source';
            $params['source'] = $filters['source'];
        }
        if (($filters['horizon'] ?? '') !== '') {
            $conditions[] = 'COALESCE(h.registration_deadline_utc, h.end_at_utc) IS NOT NULL AND COALESCE(h.registration_deadline_utc, h.end_at_utc) <= :horizon';
            $params['horizon'] = gmdate('c', time() + ((int) $filters['horizon'] * 86400));
        }

        $order = match ($filters['sort'] ?? 'ending') {
            'prize' => 'COALESCE(h.prize_amount_minor, 0) DESC, h.end_at_utc ASC',
            'noise' => 'h.low_noise_score DESC, h.end_at_utc ASC',
            'newest' => 'h.created_at DESC',
            default => 'CASE WHEN h.end_at_utc IS NULL THEN 1 ELSE 0 END, h.end_at_utc ASC',
        };

        $sql = 'SELECT h.*, s.name AS source_name FROM hackathons h LEFT JOIN sources s ON s.id = h.source_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY ' . $order . ' LIMIT 60';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function options(): array
    {
        return [
            'types' => $this->pdo->query("SELECT DISTINCT hackathon_type FROM hackathons WHERE verification_status = 'verified' AND hackathon_type IS NOT NULL AND hackathon_type != '' ORDER BY hackathon_type")->fetchAll(PDO::FETCH_COLUMN),
            'sources' => $this->pdo->query("SELECT DISTINCT platform_name FROM hackathons WHERE verification_status = 'verified' AND platform_name IS NOT NULL AND platform_name != '' ORDER BY platform_name")->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    public function summary(): array
    {
        $this->refreshStatuses();
        return [
            'verified' => (int) $this->pdo->query("SELECT COUNT(*) FROM hackathons WHERE verification_status = 'verified' AND status IN ('active', 'upcoming') AND COALESCE(registration_deadline_utc, end_at_utc) IS NOT NULL AND datetime(COALESCE(registration_deadline_utc, end_at_utc)) > datetime('now') AND ((prize_text IS NOT NULL AND prize_text != '') OR prize_amount_minor IS NOT NULL)")->fetchColumn(),
            'ending' => (int) $this->pdo->query("SELECT COUNT(*) FROM hackathons WHERE verification_status = 'verified' AND status IN ('active', 'upcoming') AND end_at_utc IS NOT NULL AND datetime(end_at_utc) > datetime('now') AND datetime(end_at_utc) <= datetime('now', '+7 days') AND ((prize_text IS NOT NULL AND prize_text != '') OR prize_amount_minor IS NOT NULL)")->fetchColumn(),
            'sources' => (int) $this->pdo->query("SELECT COUNT(*) FROM sources WHERE enabled = 1")->fetchColumn(),
            'pending_candidates' => (int) $this->pdo->query("SELECT COUNT(*) FROM discovery_candidates WHERE status = 'unreviewed'")->fetchColumn(),
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT h.*, s.name AS source_name, s.base_url AS source_base_url FROM hackathons h LEFT JOIN sources s ON s.id = h.source_id WHERE h.id = :id AND h.verification_status = 'verified' AND h.status IN ('active', 'upcoming') AND COALESCE(h.registration_deadline_utc, h.end_at_utc) IS NOT NULL AND datetime(COALESCE(h.registration_deadline_utc, h.end_at_utc)) > datetime('now') AND ((h.prize_text IS NOT NULL AND h.prize_text != '') OR h.prize_amount_minor IS NOT NULL) LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function links(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM hackathon_links WHERE hackathon_id = :id ORDER BY is_primary DESC, kind');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function checks(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verification_checks WHERE hackathon_id = :id ORDER BY checked_at DESC');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    private function refreshStatuses(): void
    {
        $this->pdo->exec("UPDATE hackathons SET status = CASE
            WHEN end_at_utc IS NOT NULL AND datetime(end_at_utc) < datetime('now') THEN 'closed'
            WHEN start_at_utc IS NOT NULL AND datetime(start_at_utc) > datetime('now') THEN 'upcoming'
            WHEN end_at_utc IS NOT NULL AND datetime(end_at_utc) >= datetime('now') THEN 'active'
            ELSE status
        END WHERE verification_status = 'verified'");
    }
}
