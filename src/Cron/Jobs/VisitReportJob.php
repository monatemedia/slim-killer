<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use PDO;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Database\Database;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;
use SlimKiller\Visits\ReportSchedule;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitReport;

/**
 * Emails the visit report to each person who chose it in Preferences, when they chose
 * it (ReportSchedule): daily, weekly or monthly, at an hour in the site's time zone.
 * Each person's report for a period goes once; if the host refuses one, cron tries
 * again on its next run.
 *
 * While nobody has chosen it, the report goes as before: yesterday's, every morning
 * after 06:00, to VISIT_REPORT_TO.
 */
final readonly class VisitReportJob implements CronJob
{
    /** The hour (site time) from which yesterday's report goes to VISIT_REPORT_TO. */
    public const int FROM_HOUR = 6;

    public function __construct(
        private PDO $pdo,
        private VisitReport $report,
        private LetterWriter $writer,
        private Mailer $mailer,
        private SiteTime $time,
        private AppConfig $config,
        private ?UserRepository $users = null,
    ) {}

    public function name(): string
    {
        return 'visit-report';
    }

    public function everyMinutes(): int
    {
        // Often enough that a report chosen for 07:00 comes soon after 07:00.
        return 15;
    }

    public function run(DateTimeImmutable $now): string
    {
        if ($this->config->mailFrom === '') {
            return 'No visit report: MAIL_FROM is empty.';
        }
        if (! Database::hasTable($this->pdo, 'visit_reports')) {
            return 'The visit counter is not set up yet. Run ' . Hammer::command('migrate') . '.';
        }

        $readers = $this->users?->visitReportReaders() ?? [];

        return $readers === [] ? $this->toFallback($now) : $this->toReaders($readers, $now);
    }

    /** @param non-empty-list<\SlimKiller\Auth\User> $readers */
    private function toReaders(array $readers, DateTimeImmutable $now): string
    {
        $local = $now->setTimezone($this->time->zone);
        $done = [];
        $failed = [];
        foreach ($readers as $reader) {
            $schedule = ReportSchedule::fromCron($reader->visitReport);
            $due = $schedule->lastDue($local);
            if ($due === null) {
                continue;
            }
            $period = $schedule->period($due);
            $sent = $this->pdo->prepare('SELECT 1 FROM visit_report_sends WHERE user_id = ? AND period = ?');
            $sent->execute([$reader->id, $period['key']]);
            if ($sent->fetchColumn() !== false) {
                continue;
            }

            $report = $this->report->forPeriod($period, $this->config->name);
            if (! $this->mailer->send($this->writer->write('visit-report', $reader->email, $report['subject'], $report['data']))) {
                $failed[] = $reader->email;

                continue;
            }
            $this->pdo->prepare('INSERT INTO visit_report_sends (user_id, period, sent_at) VALUES (?, ?, ?)')
                ->execute([$reader->id, $period['key'], $now->getTimestamp()]);
            $done[] = "{$period['key']} to {$reader->email}";
        }

        if ($failed !== []) {
            throw new JobFailed('The host did not send the visit report to ' . implode(', ', $failed) . '. Check that MAIL_FROM is a mailbox on this website\'s domain.');
        }

        return $done === [] ? 'No visit report is due.' : 'Sent the visit report: ' . implode('; ', $done) . '.';
    }

    private function toFallback(DateTimeImmutable $now): string
    {
        if ($this->config->visitReportTo === '') {
            return 'No visit report: nobody chose it in Preferences, and VISIT_REPORT_TO is empty.';
        }
        if ((int) $now->setTimezone($this->time->zone)->format('G') < self::FROM_HOUR) {
            return 'The visit report goes after 0' . self::FROM_HOUR . ':00.';
        }

        $day = $this->time->day($now->modify('-1 day'));
        $sent = $this->pdo->prepare('SELECT 1 FROM visit_reports WHERE day = ?');
        $sent->execute([$day]);
        if ($sent->fetchColumn() !== false) {
            return "Already sent the visit report for {$day}.";
        }

        $letter = $this->writer->write('visit-report', $this->config->visitReportTo, $this->report->subject($day, $this->config->name), $this->report->daily($day));
        if (! $this->mailer->send($letter)) {
            throw new JobFailed('The host did not send the visit report. Check that MAIL_FROM is a mailbox on this website\'s domain.');
        }
        $this->pdo->prepare('INSERT INTO visit_reports (day, sent_at) VALUES (?, ?)')->execute([$day, $now->getTimestamp()]);

        return "Sent the visit report for {$day} to {$this->config->visitReportTo}.";
    }
}
