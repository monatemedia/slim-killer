-- Each person chooses which emails they get, in Preferences:
--   visit_report  when the visit report comes, cron-style ("0 7 * * 1" is Mondays at
--                 07:00, site time); empty means never.
--   alerts        1 to be emailed when something stops working.
-- Defaults by role: administrators get alerts and no report, site owners the report
-- (weekly, Monday 07:00) and no alerts. ALERTS_TO and VISIT_REPORT_TO stay as the
-- fallback while nobody has chosen them.

ALTER TABLE users ADD COLUMN visit_report TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN alerts INTEGER NOT NULL DEFAULT 0;
UPDATE users SET visit_report = '0 7 * * 1', alerts = 0 WHERE role = 'owner';
UPDATE users SET visit_report = '', alerts = 1 WHERE role = 'admin';

-- Which report each person has had: one row per person and period ("weekly:2026-11-16").
CREATE TABLE visit_report_sends (
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    period      TEXT    NOT NULL,
    sent_at     INTEGER NOT NULL,
    PRIMARY KEY (user_id, period)
);
