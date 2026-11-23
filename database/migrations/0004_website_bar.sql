-- The website bar: a small button on the website, for signed-in people only, that
-- leads to the control panel ("Edit this page"). Each person chooses whether they see
-- it; signed in, they are never counted as visitors either way.

ALTER TABLE users ADD COLUMN website_bar TEXT NOT NULL DEFAULT 'show';
