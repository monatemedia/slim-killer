-- The visit counter: what visitors do on the website, counted without cookies.
-- A visitor code is made fresh each day from a secret that is then thrown away, so
-- nobody can be followed from one day to the next. Times are Unix timestamps (UTC);
-- days are in the site's time zone (TIMEZONE in .env).

-- Every event. Kept for good unless the site sets VISIT_EVENTS_MONTHS; the daily totals
-- always stay.
CREATE TABLE visit_events (
    id        INTEGER PRIMARY KEY,
    at        INTEGER NOT NULL,
    day       TEXT    NOT NULL,
    visitor   TEXT    NOT NULL,
    kind      TEXT    NOT NULL CHECK (kind IN ('page', 'tap', 'form')),
    path      TEXT    NOT NULL,
    label     TEXT    NOT NULL DEFAULT '',
    source    TEXT    NOT NULL,
    medium    TEXT    NOT NULL DEFAULT '',
    campaign  TEXT    NOT NULL DEFAULT '',
    device    TEXT    NOT NULL CHECK (device IN ('phone', 'tablet', 'computer'))
);

CREATE INDEX visit_events_by_visitor ON visit_events (day, visitor);

-- Daily totals: one row for each day and each mix of what was counted. No visitor codes.
CREATE TABLE visit_days (
    day       TEXT    NOT NULL,
    kind      TEXT    NOT NULL,
    path      TEXT    NOT NULL,
    label     TEXT    NOT NULL,
    source    TEXT    NOT NULL,
    medium    TEXT    NOT NULL,
    campaign  TEXT    NOT NULL,
    device    TEXT    NOT NULL,
    visitors  INTEGER NOT NULL,
    events    INTEGER NOT NULL,
    PRIMARY KEY (day, kind, path, label, source, medium, campaign, device)
);

-- The days already added up, so each day is added up once.
CREATE TABLE visit_days_done (
    day  TEXT    PRIMARY KEY,
    at   INTEGER NOT NULL
);

-- The days the daily visit report has been sent for, so each goes once.
CREATE TABLE visit_reports (
    day      TEXT    PRIMARY KEY,
    sent_at  INTEGER NOT NULL
);

-- One secret for each day, deleted when the day is over.
CREATE TABLE visitor_secrets (
    day     TEXT PRIMARY KEY,
    secret  TEXT NOT NULL
);
