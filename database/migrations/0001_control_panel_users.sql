-- People who can use the control panel, and everything that keeps their login safe.
-- Times are Unix timestamps (seconds, UTC).

CREATE TABLE users (
    id                  INTEGER PRIMARY KEY,
    email               TEXT    NOT NULL UNIQUE,
    name                TEXT    NOT NULL,
    role                TEXT    NOT NULL CHECK (role IN ('owner', 'admin')),
    password_hash       TEXT,
    totp_secret         TEXT,
    totp_last_counter   INTEGER,
    created_at          INTEGER NOT NULL
);

-- One welcome link per person at a time: set a password, then two-factor.
CREATE TABLE user_invites (
    user_id     INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    token_hash  TEXT    NOT NULL UNIQUE,
    expires_at  INTEGER NOT NULL
);

CREATE TABLE recovery_codes (
    id          INTEGER PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    code_hash   TEXT    NOT NULL,
    used_at     INTEGER
);

-- Only a hash of each session's cookie is stored.
CREATE TABLE sessions (
    id          TEXT    PRIMARY KEY,
    user_id     INTEGER REFERENCES users (id) ON DELETE CASCADE,
    data        TEXT    NOT NULL,
    created_at  INTEGER NOT NULL,
    expires_at  INTEGER NOT NULL
);

CREATE TABLE login_attempts (
    key         TEXT    NOT NULL,
    at          INTEGER NOT NULL
);
CREATE INDEX login_attempts_key ON login_attempts (key, at);

CREATE TABLE audit_log (
    id          INTEGER PRIMARY KEY,
    at          INTEGER NOT NULL,
    user_id     INTEGER,
    event       TEXT    NOT NULL,
    ip          TEXT    NOT NULL DEFAULT ''
);
