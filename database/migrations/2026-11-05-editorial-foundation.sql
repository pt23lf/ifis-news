-- IFIS News editorial foundation (SQLite)
-- Preparation only. Do not apply against production until backups, app logic,
-- CSRF/session protections and the deployment migration runner are verified.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS editorial_users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL COLLATE NOCASE UNIQUE,
 display_name TEXT NOT NULL,
 password_hash TEXT,
 role TEXT NOT NULL DEFAULT 'contributor' CHECK(role IN ('admin','editor','contributor','reader')),
 status TEXT NOT NULL DEFAULT 'invited' CHECK(status IN ('invited','active','suspended')),
 preferred_language TEXT NOT NULL DEFAULT 'de',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS editorial_invitations (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL COLLATE NOCASE,
 role TEXT NOT NULL CHECK(role IN ('admin','editor','contributor','reader')),
 token_hash TEXT NOT NULL UNIQUE,
 invited_by INTEGER NOT NULL REFERENCES editorial_users(id),
 expires_at TEXT NOT NULL,
 accepted_at TEXT,
 revoked_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS editorial_invitations_email ON editorial_invitations(email);

CREATE TABLE IF NOT EXISTS editorial_submissions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 kind TEXT NOT NULL CHECK(kind IN ('external_link','original')),
 title TEXT NOT NULL,
 source_url TEXT,
 body TEXT,
 own_context TEXT,
 source_language TEXT,
 place_name TEXT,
 latitude REAL CHECK(latitude BETWEEN -90 AND 90),
 longitude REAL CHECK(longitude BETWEEN -180 AND 180),
 created_by INTEGER NOT NULL REFERENCES editorial_users(id),
 reviewed_by INTEGER REFERENCES editorial_users(id),
 status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','pending','changes_requested','approved','rejected','published')),
 review_note TEXT,
 published_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK ((kind='external_link' AND source_url IS NOT NULL AND length(trim(source_url))>0)
     OR (kind='original' AND body IS NOT NULL AND length(trim(body))>0))
);
CREATE INDEX IF NOT EXISTS editorial_submissions_queue ON editorial_submissions(status, created_at);
CREATE INDEX IF NOT EXISTS editorial_submissions_author ON editorial_submissions(created_by, created_at);

CREATE TABLE IF NOT EXISTS editorial_audit_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 actor_id INTEGER REFERENCES editorial_users(id),
 submission_id INTEGER REFERENCES editorial_submissions(id),
 action TEXT NOT NULL,
 details_json TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS editorial_audit_submission ON editorial_audit_log(submission_id, created_at);

CREATE TABLE IF NOT EXISTS editorial_translations (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 submission_id INTEGER NOT NULL REFERENCES editorial_submissions(id) ON DELETE CASCADE,
 language TEXT NOT NULL,
 field TEXT NOT NULL CHECK(field IN ('title','body','own_context')),
 translated_text TEXT NOT NULL,
 provider TEXT NOT NULL DEFAULT 'manual',
 source_hash TEXT NOT NULL,
 reviewed_by INTEGER REFERENCES editorial_users(id),
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(submission_id,language,field,source_hash)
);
