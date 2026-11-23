<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\Backups;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;

/**
 * `hammer backup:check`: a test restore. A backup is only worth having once it has
 * been restored, so this fetches the newest one and opens it as a restore would,
 * without touching the live database.
 */
final readonly class BackupCheckCommand implements Command
{
    public function __construct(
        private Backups $backups,
    ) {}

    public function name(): string
    {
        return 'backup:check';
    }

    public function description(): string
    {
        return 'Test-restore the newest backup into a temporary copy';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $output->line($this->backups->checkNewest(), 'green');

            return 0;
        } catch (BackupFailed $problem) {
            $output->line($problem->getMessage(), 'red');

            return 1;
        }
    }
}
