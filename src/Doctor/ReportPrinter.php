<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use SlimKiller\Console\Output;

/** Prints a report the way `hammer doctor` does, for `hammer site:check` too. */
final class ReportPrinter
{
    /**
     * @param string $ready What the last line says when nothing failed, before the counts.
     * @param string $notReady What it says otherwise; %s is replaced by the counts.
     */
    public static function print(Report $report, Output $output, string $heading, string $ready, string $notReady): void
    {
        $output->line();
        $output->line($heading, 'bold');
        foreach ($report->sections as $section) {
            $output->line();
            $output->line($section->title, 'yellow');
            foreach ($section->results as $result) {
                $label = $output->paint(str_pad('[' . $result->status->label() . ']', 7), $result->status->colour());
                $detail = $result->detail !== '' ? ': ' . $result->detail : '';
                $output->line('  ' . $label . ' ' . $result->title . $detail);
                if ($result->hint !== '') {
                    $output->line('          ' . $result->hint, 'grey');
                }
            }
        }
        $output->line();
        $summary = sprintf(
            '%d passed, %d %s, %d failed',
            $report->count(Status::Pass),
            $report->count(Status::Warn),
            $report->count(Status::Warn) === 1 ? 'warning' : 'warnings',
            $report->count(Status::Fail),
        );
        if ($report->passed()) {
            $output->line($ready . ' ' . $summary . '.', 'green');
        } else {
            $output->line(sprintf($notReady, $summary), 'red');
        }
        $output->line();
    }
}
