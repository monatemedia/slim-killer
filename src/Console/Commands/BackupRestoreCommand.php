<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\Backups;
use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\Clock;

/**
 * `hammer backup:restore`: lists the backups. `hammer backup:restore <name> --yes`
 * puts one back as the live database, after checking it, and keeps the database as it
 * was in storage/backups/ first. Anyone signed in since that backup signs in again.
 */
final readonly class BackupRestoreCommand implements Command
{
    public function __construct(
        private Backups $backups,
        private Clock $clock,
    ) {}

    public function name(): string
    {
        return 'backup:restore';
    }

    public function description(): string
    {
        return 'List the backups, or put one back as the database (<name> --yes)';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $name = $input->arguments[0] ?? null;
            if ($name === null) {
                return $this->list($input, $output);
            }
            if (! $input->flag('yes')) {
                $output->line("This replaces the live database with {$name}. Everything since that backup is lost:", 'yellow');
                $output->line('people added, passwords changed, visits counted. The database as it is now is kept first.');
                $output->line('To go ahead, run: ' . $input->script . " backup:restore {$name} --yes", 'cyan');

                return 1;
            }

            $restored = $this->backups->restore($name, $this->clock->now());
            $output->line("Restored {$name} from {$restored['from']}: {$restored['summary']}.", 'green');
            $output->line("The database as it was is kept as {$restored['before']}.");

            return 0;
        } catch (BackupFailed $problem) {
            $output->line($problem->getMessage(), 'red');

            return 1;
        }
    }

    private function list(Input $input, Output $output): int
    {
        $names = $this->backups->names();
        $output->line('On this server (the last 7):', 'green');
        foreach ($names['here'] === [] ? ['(none yet)'] : $names['here'] as $name) {
            $output->line('  ' . $name);
        }
        $output->line();
        if ($names['drive'] === null) {
            $output->line('Google Drive is not connected.', 'yellow');
        } else {
            $output->line('On Google Drive (' . count($names['drive']) . ', the newest 10 shown):', 'green');
            foreach ($names['drive'] === [] ? ['(none yet)'] : array_slice($names['drive'], 0, 10) as $name) {
                $output->line('  ' . $name);
            }
        }
        $output->line();
        $output->line('To put one back: ' . $input->script . ' backup:restore <name> --yes', 'cyan');

        return 0;
    }
}
