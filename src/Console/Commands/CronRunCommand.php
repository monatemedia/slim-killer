<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\CronRunner;
use SlimKiller\Cron\JobOutcome;

/**
 * The one cron line: runs whatever is due. Quiet when all is well, so the host's cron
 * log only shows problems. -v shows every job.
 */
final class CronRunCommand implements Command
{
    public function __construct(
        private readonly CronRunner $runner,
    ) {}

    public function name(): string
    {
        return 'cron:run';
    }

    public function description(): string
    {
        return 'Run what is due (add this to cron, every minute)';
    }

    public function run(Input $input, Output $output): int
    {
        $failed = false;
        foreach ($this->runner->run() as $report) {
            if ($report->outcome === JobOutcome::Failed) {
                $failed = true;
                $output->line("{$report->job}: {$report->message}", 'red');
            } elseif ($input->flag('v')) {
                $output->line($output->paint(str_pad($report->job, 14), 'green') . $report->message);
            }
        }

        return $failed ? 1 : 0;
    }
}
