-- Table names follow the glossary (wiki: Glossary): "welcome link", "sign in".
-- Released migrations are never edited, so this renames what 0001 created.

ALTER TABLE user_invites RENAME TO welcome_links;

ALTER TABLE login_attempts RENAME TO sign_in_attempts;
DROP INDEX login_attempts_key;
CREATE INDEX sign_in_attempts_key ON sign_in_attempts (key, at);
