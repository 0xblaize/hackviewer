ALTER TABLE discovery_candidates ADD COLUMN reviewed_at TEXT;
ALTER TABLE discovery_candidates ADD COLUMN review_note TEXT;
ALTER TABLE discovery_candidates ADD COLUMN converted_hackathon_id INTEGER REFERENCES hackathons(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_candidates_review_queue ON discovery_candidates(status, updated_at);
