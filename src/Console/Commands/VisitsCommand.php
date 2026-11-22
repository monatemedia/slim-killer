<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\Clock;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitLog;
use SlimKiller\Visits\VisitReport;

/**
 * What the visit counter counted over the last days: `hammer visits` for 7 days,
 * `hammer visits --days=30` for longer. Today is added up after midnight, so the
 * report ends yesterday.
 */
final class VisitsCommand implements Command
{
    public function __construct(
        private readonly VisitLog $log,
        private readonly VisitReport $report,
        private readonly SiteTime $time,
        private readonly Clock $clock,
    ) {}

    public function name(): string
    {
        return 'visits';
    }

    public function description(): string
    {
        return 'Show what the visit counter counted (--days=7)';
    }

    public function run(Input $input, Output $output): int
    {
        $days = max(1, (int) ($input->option('days') ?? 7));
        $now = $this->clock->now();
        $last = $this->time->day($now->modify('-1 day'));
        $first = $this->time->day($now->modify("-{$days} days"));

        $totals = $this->log->summary($first, $last);
        $output->line("Visits from {$first} to {$last}", 'green');
        $output->line();
        $output->line(sprintf('  %-22s %d', 'Visitors (day by day)', $totals['visitors']));
        $output->line(sprintf('  %-22s %d', 'Pages opened', $totals['pages']));
        $output->line(sprintf('  %-22s %d', 'Buttons tapped', $totals['taps']));
        $output->line(sprintf('  %-22s %d', 'Forms sent', $totals['forms']));

        foreach ($this->report->lists($first, $last) as $list) {
            $output->line();
            $output->line($list['heading'], 'green');
            foreach ($list['rows'] as $row) {
                $output->line(sprintf('  %6d  %s', $row['events'], $row['name'] === '' ? '(none)' : $row['name']));
            }
        }

        return 0;
    }
}
