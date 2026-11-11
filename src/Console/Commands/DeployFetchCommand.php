<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Publishing\Fetcher;

/**
 * Fetches the newest build and makes it live. The webhook does the same; cron runs this
 * every few minutes as a safety net for a missed webhook call.
 */
final class DeployFetchCommand implements Command
{
    public function __construct(
        private readonly Fetcher $fetcher,
    ) {}

    public function name(): string
    {
        return 'deploy:fetch';
    }

    public function description(): string
    {
        return 'Fetch the newest build from GitHub and make it live';
    }

    public function run(Input $input, Output $output): int
    {
        $started = hrtime(true);
        $result = $this->fetcher->fetch();
        $seconds = number_format((hrtime(true) - $started) / 1e9, 1);

        $output->line($result->message, match ($result->outcome) {
            'switched' => 'green',
            'failed' => 'red',
            default => null,
        });
        if ($result->outcome === 'switched') {
            $output->line("Took {$seconds} s.", 'grey');
        }

        return $result->isFailed() ? 1 : 0;
    }
}
