CREATE TABLE IF NOT EXISTS sorsa_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_date TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    fetched_count INTEGER NOT NULL DEFAULT 0,
    created_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    duplicate_count INTEGER NOT NULL DEFAULT 0,
    error_count INTEGER NOT NULL DEFAULT 0,
    error_message TEXT
);

CREATE TABLE IF NOT EXISTS sorsa_batch_queries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER NOT NULL,
    query_ordinal INTEGER NOT NULL,
    query_text TEXT NOT NULL,
    status TEXT NOT NULL,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    fetched_count INTEGER NOT NULL DEFAULT 0,
    created_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    duplicate_count INTEGER NOT NULL DEFAULT 0,
    error_message TEXT,
    raw_record_id INTEGER,
    UNIQUE(batch_id, query_ordinal),
    FOREIGN KEY (batch_id) REFERENCES sorsa_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (raw_record_id) REFERENCES raw_ingestion_records(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_sorsa_batches_status_date ON sorsa_batches(status, batch_date);
CREATE INDEX IF NOT EXISTS idx_sorsa_batch_queries_batch ON sorsa_batch_queries(batch_id);
