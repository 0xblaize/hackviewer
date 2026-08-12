CREATE TABLE IF NOT EXISTS sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    kind TEXT NOT NULL,
    base_url TEXT,
    enabled INTEGER NOT NULL DEFAULT 1,
    last_success_at TEXT,
    last_error_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS hackathons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER,
    source_event_id TEXT,
    canonical_url TEXT NOT NULL UNIQUE,
    official_url TEXT NOT NULL,
    title TEXT NOT NULL,
    organizer_name TEXT,
    platform_name TEXT,
    description TEXT,
    hackathon_type TEXT,
    start_at_utc TEXT,
    end_at_utc TEXT,
    registration_deadline_utc TEXT,
    timezone_name TEXT,
    prize_amount_minor INTEGER,
    prize_currency TEXT,
    prize_text TEXT,
    participant_count INTEGER,
    participant_count_as_of_utc TEXT,
    online_or_location TEXT,
    location_text TEXT,
    status TEXT NOT NULL DEFAULT 'unknown',
    verification_status TEXT NOT NULL DEFAULT 'unreviewed',
    legitimacy_score INTEGER,
    legitimacy_notes TEXT,
    what_to_know TEXT,
    low_noise_score INTEGER NOT NULL DEFAULT 0,
    last_seen_at TEXT,
    last_verified_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS hackathon_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hackathon_id INTEGER NOT NULL,
    kind TEXT NOT NULL,
    url TEXT NOT NULL,
    label TEXT NOT NULL,
    is_primary INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (hackathon_id) REFERENCES hackathons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS raw_ingestion_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER,
    external_key TEXT,
    request_url TEXT NOT NULL,
    retrieved_at TEXT NOT NULL,
    http_status INTEGER,
    content_type TEXT,
    content_hash TEXT,
    payload_path TEXT,
    parser_version TEXT,
    parse_status TEXT NOT NULL DEFAULT 'pending',
    parse_error TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS verification_checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hackathon_id INTEGER NOT NULL,
    check_type TEXT NOT NULL,
    result TEXT NOT NULL,
    evidence_url TEXT,
    evidence_excerpt TEXT,
    checked_at TEXT NOT NULL,
    FOREIGN KEY (hackathon_id) REFERENCES hackathons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS discovery_candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER,
    external_key TEXT NOT NULL,
    post_url TEXT NOT NULL,
    author_handle TEXT,
    text TEXT NOT NULL,
    posted_at TEXT,
    engagement_json TEXT,
    raw_record_id INTEGER,
    status TEXT NOT NULL DEFAULT 'unreviewed',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(source_id, external_key),
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL,
    FOREIGN KEY (raw_record_id) REFERENCES raw_ingestion_records(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_candidates_status ON discovery_candidates(status);
CREATE INDEX IF NOT EXISTS idx_candidates_posted_at ON discovery_candidates(posted_at);

CREATE TABLE IF NOT EXISTS ingestion_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_id INTEGER,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    fetched_count INTEGER NOT NULL DEFAULT 0,
    created_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    rejected_count INTEGER NOT NULL DEFAULT 0,
    error_count INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_hackathons_status_end ON hackathons(status, end_at_utc);
CREATE INDEX IF NOT EXISTS idx_hackathons_verification_end ON hackathons(verification_status, end_at_utc);
CREATE INDEX IF NOT EXISTS idx_hackathons_type ON hackathons(hackathon_type);
CREATE INDEX IF NOT EXISTS idx_hackathons_source ON hackathons(source_id);
