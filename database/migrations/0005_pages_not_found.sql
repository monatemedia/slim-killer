-- Pages not found: the visit counter counts the site's 404 page as its own kind,
-- "missing", with where the broken link was in the label. SQLite can't change a CHECK,
-- so the events table is made again with the new kind allowed, and its rows copied.

CREATE TABLE visit_events_new (
    id        INTEGER PRIMARY KEY,
    at        INTEGER NOT NULL,
    day       TEXT    NOT NULL,
    visitor   TEXT    NOT NULL,
    kind      TEXT    NOT NULL CHECK (kind IN ('page', 'tap', 'form', 'missing')),
    path      TEXT    NOT NULL,
    label     TEXT    NOT NULL DEFAULT '',
    source    TEXT    NOT NULL,
    medium    TEXT    NOT NULL DEFAULT '',
    campaign  TEXT    NOT NULL DEFAULT '',
    device    TEXT    NOT NULL CHECK (device IN ('phone', 'tablet', 'computer'))
);

INSERT INTO visit_events_new (id, at, day, visitor, kind, path, label, source, medium, campaign, device)
    SELECT id, at, day, visitor, kind, path, label, source, medium, campaign, device FROM visit_events;

DROP TABLE visit_events;

ALTER TABLE visit_events_new RENAME TO visit_events;

CREATE INDEX visit_events_by_visitor ON visit_events (day, visitor);
