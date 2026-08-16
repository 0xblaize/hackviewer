ALTER TABLE hackathons ADD COLUMN canonical_key TEXT;
ALTER TABLE discovery_candidates ADD COLUMN lead_key TEXT;
CREATE INDEX IF NOT EXISTS idx_hackathons_canonical_key ON hackathons(canonical_key);
CREATE INDEX IF NOT EXISTS idx_candidates_lead_key ON discovery_candidates(lead_key);
