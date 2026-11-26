-- The browsers each person has signed in from, so a sign-in from a new one is emailed
-- to them. A browser keeps a random value in its sk_browser cookie; only its hash is
-- stored here. One browser can be known to several people (a shared office computer).

CREATE TABLE known_browsers (
    id          INTEGER PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash  TEXT    NOT NULL,
    name        TEXT    NOT NULL DEFAULT '',
    first_seen  INTEGER NOT NULL,
    last_seen   INTEGER NOT NULL,
    UNIQUE (user_id, token_hash)
);
