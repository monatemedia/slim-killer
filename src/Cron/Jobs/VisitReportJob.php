<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use PDO;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Database\Database;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitReport;

/**
 * Emails yesterday's visits every morning, after 06:00 in the site's time zone, to
 * VISIT_REPORT_TO. Each day's report goes once; if the host refuses it, cron tries
 * again the next hour.
 */
final readonly class VisitReportJob implements CronJob
{
    /** The hour (site time) from which yesterday's report goes. */
    public const int FROM_HOUR = 6;

    public function __construct(
        private PDO $pdo,
        private VisitReport $report,
        private LetterWriter $writer,
        private Mailer $mailer,
        private SiteTime $time,
        private AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'visit-report';
    }

    public function everyMinutes(): int
    {
        return 60;
    }

    public function run(DateTimeImmutable $now): string
    {
        if ($this->config->visitReportTo === '') {
            return 'No visit report: VISIT_REPORT_TO is empty.';
        }
        if ($this->config->mailFrom === '') {
            return 'No visit report: MAIL_FROM is empty.';
        }
        if (! Database::hasTable($this->pdo, 'visit_reports')) {
            return 'The visit counter is not set up yet. Run ' . Hammer::command('migrate') . '.';
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
