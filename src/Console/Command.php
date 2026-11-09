<?php

declare(strict_types=1);

namespace SlimKiller\Console;

/**
 * One hammer command. Register it in the container's 'console.commands' list.
 *
 * A colon in the name ("cron:heartbeat") groups it under that prefix in `hammer list`.
 */
interface Command
{
    public function name(): string;

    public function description(): string;

    /** Returns the process exit code: 0 for success. */
    public function run(Input $input, Output $output): int;
}
