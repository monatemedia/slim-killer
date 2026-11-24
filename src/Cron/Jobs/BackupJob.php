<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\Backups;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\SiteTime;

/**
 * Backs up the database once a day, after 02:00 in the site's time zone, when the
 * website is quiet. A backup that did not reach Google Drive is sent again the next hour.
 */
final readonly class BackupJob implements CronJob
{
    /** The hour (site time) from which the day's backup is made. */
    public const int FROM_HOUR = 2;

    public function __construct(
        private Backups $backups,
        private SiteTime $time,
        private AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'backup';
    }

    public function everyMinutes(): int
    {
        return 60;
    }

    public function run(DateTimeImmutable $now): string
    {
        if ($this->config->backupsOff) {
            return 'Backups are off (BACKUPS="off" in .env).';
        }
        if ((int) $now->setTimezone($this->time->zone)->format('G') < self::FROM_HOUR) {
            return 'The day\'s backup is made after 0' . self::FROM_HOUR . ':00.';
        }

        try {
            return $this->backups->daily($now);
        } catch (BackupFailed $problem) {
            throw new JobFailed($problem->getMessage(), 0, $problem);
        }
    }
}
