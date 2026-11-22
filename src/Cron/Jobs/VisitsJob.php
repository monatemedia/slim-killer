<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use PDO;
use SlimKiller\Cron\CronJob;
use SlimKiller\Database\Database;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitLog;
use SlimKiller\Visits\VisitorCodes;

/**
 * Looks after the visit counter, every hour: adds up each finished day into the daily
 * totals, and deletes the secrets of finished days (so their visitor codes can't be
 * worked out again). Single events stay for good unless VISIT_EVENTS_MONTHS is set: a
 * site that must follow France's rules sets 25, and older events are then deleted.
 * The daily totals always stay.
 */
final readonly class VisitsJob implements CronJob
{
    public function __construct(
        private PDO $pdo,
        private VisitLog $log,
        private VisitorCodes $codes,
        private SiteTime $time,
        private AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'visits';
    }

    public function everyMinutes(): int
    {
        return 60;
    }

    public function run(DateTimeImmutable $now): string
    {
        if (! Database::hasTable($this->pdo, 'visit_events')) {
            return 'The visit counter is not set up yet. Run hammer migrate.';
        }

        $today = $this->time->day($now);
        $added = $this->log->addUpBefore($today, $now);
        $this->codes->forgetBefore($today);
        $said = $added === [] ? 'No new days to add up.' : 'Added up ' . implode(', ', $added) . '.';

        $months = $this->config->visitEventsMonths;
        if ($months === null) {
            return $said;
        }
        $deleted = $this->log->deleteEventsBefore($this->time->day($now->modify("-{$months} months")));

        if ($deleted === 0) {
            return $said;
        }

        return $said . ' Deleted ' . ($deleted === 1 ? '1 event' : "{$deleted} events") . " older than {$months} months.";
    }
}
