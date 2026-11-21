<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

use DateTimeImmutable;

/**
 * One thing cron does, run by `hammer cron:run`. Register it in the container's
 * 'cron.jobs' list; a client site adds its own with DI\add([...]) in config/site.php.
 */
interface CronJob
{
    /** A short name for the job's lock and its record, like "deploy-fetch". */
    public function name(): string;

    /** How often it runs, in minutes. */
    public function everyMinutes(): int;

    /**
     * Does the work and says in one line what it did.
     *
     * @throws JobFailed when it could not do the work, with what went wrong
     */
    public function run(DateTimeImmutable $now): string;
}
