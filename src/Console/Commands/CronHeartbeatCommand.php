<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Support\Clock;

/**
 * Records that cron ran, and nothing else. The cron line before `cron:run`, kept so a
 * host that still has it keeps working; `hammer doctor` asks for the new line.
 */
final class CronHeartbeatCommand implements Command
{
    public function __construct(
        private readonly Heartbeat $heartbeat,
        private readonly Clock $clock,
    ) {}

    public function name(): string
    {
        return 'cron:heartbeat';
    }

    public function description(): string
    {
        return 'Record that cron ran (the older cron line: use cron:run)';
    }

    public function run(Input $input, Output $output): int
    {
        if (! $this->heartbeat->record($this->clock->now(), 'cron:heartbeat')) {
            $output->line("Could not write {$this->heartbeat->file()}. Is the storage folder writable?", 'red');

            return 1;
        }

        return 0;
    }
}
