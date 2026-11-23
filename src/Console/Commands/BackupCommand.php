<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\Backups;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\Clock;

/** `hammer backup`: a backup now, besides cron's daily one (before an update, say). */
final readonly class BackupCommand implements Command
{
    public function __construct(
        private Backups $backups,
        private Clock $clock,
    ) {}

    public function name(): string
    {
        return 'backup';
    }

    public function description(): string
    {
        return 'Back up the database now, here and to Google Drive';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $output->line($this->backups->backUp($this->clock->now()), 'green');

            return 0;
        } catch (BackupFailed $problem) {
            $output->line($problem->getMessage(), 'red');

            return 1;
        }
    }
}
