<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\Report;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\Status;
use SlimKiller\Doctor\WebProbe\WebTarget;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Setup\SetupLinkNotSaved;

final class DoctorCommand implements Command
{
    public function __construct(
        private readonly Doctor $doctor,
        private readonly ReportStore $reports,
        private readonly SetupLink $setupLink,
    ) {}

    public function name(): string
    {
        return 'doctor';
    }

    public function description(): string
    {
        return 'Check that this host has what Slim Killer needs (--url=... --webroot=... to include the website)';
    }

    public function run(Input $input, Output $output): int
    {
        $url = $input->option('url');
        $webRoot = $input->option('webroot');

        if (($url === null) !== ($webRoot === null)) {
            $output->line('Give both --url and --webroot, or neither.', 'red');
            $output->line('  php hammer doctor --url=https://example.com --webroot=~/domains/example.com/public_html');

            return 2;
        }

        $report = $this->doctor->examine(
            $url !== null && $webRoot !== null ? WebTarget::from($url, $webRoot) : null,
        );

        $this->render($report, $output);

        if (! $this->reports->save($report)) {
            $output->line('The report could not be saved for the setup page. Is the storage folder writable?', 'red');
        }
        $this->printSetupLink($url, $output);

        return $report->passed() ? 0 : 1;
    }

    private function render(Report $report, Output $output): void
    {
        $output->line();
        $output->line('Slim Killer doctor', 'bold');

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
            '%d passed, %d warnings, %d failed',
            $report->count(Status::Pass),
            $report->count(Status::Warn),
            $report->count(Status::Fail),
        );

        if ($report->passed()) {
            $output->line('Ready for Slim Killer. ' . $summary . '.', 'green');
        } else {
            $output->line('Not ready yet. ' . $summary . '. Fix the FAIL lines above and run the doctor again.', 'red');
        }
        $output->line();
    }

    /**
     * Every run hands out a fresh private link to the control panel's setup page, which
     * shows this report in a browser. Running hammer on the server is the proof of access.
     */
    private function printSetupLink(?string $url, Output $output): void
    {
        try {
            $path = SetupLink::pathFor($this->setupLink->issue());
        } catch (SetupLinkNotSaved $e) {
            $output->line('No setup page link: ' . $e->getMessage(), 'red');
            $output->line();

            return;
        }

        $output->line('Setup page (private, works for one hour):', 'bold');
        $output->line($url !== null ? '  ' . rtrim($url, '/') . $path : '  ' . $path . '   (add it to your website\'s address)');
        $output->line();
    }
}
