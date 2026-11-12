<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Publishing\Fetcher;

/** Goes back to the release before the live one. It stays live until a new build. */
final class DeployRollbackCommand implements Command
{
    public function __construct(
        private readonly Fetcher $fetcher,
    ) {}

    public function name(): string
    {
        return 'deploy:rollback';
    }

    public function description(): string
    {
        return 'Go back to the previous release (until the next new build)';
    }

    public function run(Input $input, Output $output): int
    {
        $result = $this->fetcher->rollback();
        $output->line($result->message, $result->isFailed() ? 'red' : 'green');

        return $result->isFailed() ? 1 : 0;
    }
}
