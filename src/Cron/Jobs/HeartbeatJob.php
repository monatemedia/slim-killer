<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Cron\JobFailed;

/** Notes that cron ran, every minute, for `hammer doctor`. */
final readonly class HeartbeatJob implements CronJob
{
    public function __construct(
        private Heartbeat $heartbeat,
    ) {}

    public function name(): string
    {
        return 'heartbeat';
    }

    public function everyMinutes(): int
    {
        return 1;
    }

    public function run(DateTimeImmutable $now): string
    {
        if (! $this->heartbeat->record($now, 'cron:run')) {
            throw new JobFailed("Could not write {$this->heartbeat->file()}. Is the storage folder writable?");
        }

        return 'Noted that cron ran.';
    }
}
