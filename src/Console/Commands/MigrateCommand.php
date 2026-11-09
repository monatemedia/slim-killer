<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Database\Migrator;

final class MigrateCommand implements Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Bring the database up to date';
    }

    public function run(Input $input, Output $output): int
    {
        $ran = $this->migrator->migrate();

        if ($ran === []) {
            $output->line('The database is up to date.', 'green');
        }
        foreach ($ran as $name) {
            $output->line('Migrated: ' . $name, 'green');
        }

        return 0;
    }
}
