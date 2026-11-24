<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use SlimKiller\Cron\CronJob;
use SlimKiller\Updates\UpdateCheckFailed;
use SlimKiller\Updates\Updates;

/**
 * Asks GitHub for the newest Slim Killer once a day, for the dashboard. GitHub being
 * out of reach for a day is not worth an alert: the doctor checks the connection.
 */
final readonly class UpdateCheckJob implements CronJob
{
    public function __construct(
        private Updates $updates,
    ) {}

    public function name(): string
    {
        return 'update-check';
    }

    public function everyMinutes(): int
    {
        return 24 * 60;
    }

    public function run(DateTimeImmutable $now): string
    {
        try {
            $latest = $this->updates->check();
        } catch (UpdateCheckFailed $problem) {
            return 'Could not ask GitHub for the newest Slim Killer: ' . $problem->getMessage() . '. Trying again tomorrow.';
        }
        $notice = $this->updates->newer();

        return $notice === null
            ? "The newest Slim Killer is v{$latest}; this is it."
            : "Slim Killer v{$notice->latest} is out; this is v{$notice->installed}.";
    }
}
