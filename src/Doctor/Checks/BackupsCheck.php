<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use DateTimeImmutable;
use SlimKiller\Backups\BackupEncryption;
use SlimKiller\Backups\Backups;
use SlimKiller\Backups\GoogleDrive;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\Clock;
use SlimKiller\Visits\SiteTime;

/**
 * Are backups being made, and do they leave this server? A backup only on the same
 * hosting account is lost with it, so a site without Google Drive gets a warning.
 */
final readonly class BackupsCheck implements HostCheck
{
    /** A backup older than this many days means the daily one has stopped. */
    public const int STALE_AFTER_DAYS = 2;

    public function __construct(
        private Backups $backups,
        private GoogleDrive $drive,
        private BackupEncryption $encryption,
        private SiteTime $time,
        private Clock $clock,
    ) {}

    public function check(): array
    {
        $last = $this->backups->last();
        if ($last === null) {
            return [CheckResult::warn('No backup yet', 'cron makes one each day after 02:00', 'Run hammer backup to make one now.')];
        }

        $days = (new DateTimeImmutable($last['day']))->diff(new DateTimeImmutable($this->time->day($this->clock->now())))->days;
        if ($days > self::STALE_AFTER_DAYS) {
            return [CheckResult::fail('Backups have stopped', "the last is {$last['file']}, {$days} days ago", 'Check that cron runs, then run hammer backup.')];
        }
        if (! $this->drive->isSetUp()) {
            return [CheckResult::warn('Backups stay on this server', "the last is {$last['file']}", 'Give them a copy off this server: see the wiki page Backups.')];
        }
        if (! $this->encryption->isSetUp()) {
            return [CheckResult::fail('Backups can\'t go to Google Drive', 'BACKUP_KEY is empty, and they are only sent encrypted', 'Run hammer backup:key, and save the key in your password manager.')];
        }
        if (! $this->drive->isConnected()) {
            return [CheckResult::warn('Google Drive is not connected', "the last backup, {$last['file']}, is on this server only", 'Run hammer backup:connect.')];
        }
        if (! $last['sent']) {
            return [CheckResult::warn('The last backup did not reach Google Drive', $last['file'], 'Run hammer backup to see why.')];
        }

        return [CheckResult::pass('Backups', "the last is {$last['file']}, on Google Drive too, encrypted")];
    }
}
