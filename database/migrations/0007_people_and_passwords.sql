-- People manage their own passwords, and site owners and administrators manage people
-- from the panel.
--
-- password_history: the hashes of each person's last three passwords (the current one
-- included), so a new password can't be one of them. Kept through a reset.
-- password_resets: "Forgot your password?" links, one per person, valid for an hour.
-- Only a hash of the link's token is stored. Two-factor is still asked afterwards.

CREATE TABLE password_history (
    id          INTEGER PRIMARY KEY,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    hash        TEXT    NOT NULL,
    set_at      INTEGER NOT NULL
);
CREATE INDEX password_history_user ON password_history (user_id, id);

CREATE TABLE password_resets (
    user_id     INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    token_hash  TEXT    NOT NULL UNIQUE,
    expires_at  INTEGER NOT NULL
);
