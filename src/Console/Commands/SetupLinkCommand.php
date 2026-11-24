<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Setup\SetupLinkNotSaved;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;

/**
 * `hammer setup:link`: a private link to the control panel's setup page, which shows the
 * doctor's last report in a browser. Only someone who can run hammer on the server can
 * get one. It works for one hour, and each new link replaces the last.
 */
final readonly class SetupLinkCommand implements Command
{
    public function __construct(
        private SetupLink $setupLink,
        private ReportStore $reports,
        private AppConfig $config,
    ) {}

    public function name(): string
    {
        return 'setup:link';
    }

    public function description(): string
    {
        return 'A private link to the setup page, for one hour (--url=... if APP_URL is empty)';
    }

    public function run(Input $input, Output $output): int
    {
        try {
            $path = SetupLink::pathFor($this->setupLink->issue());
        } catch (SetupLinkNotSaved $problem) {
            $output->line('No setup page link: ' . $problem->getMessage(), 'red');

            return 1;
        }

        $url = $input->option('url') ?? $this->config->url;
        $output->line('Setup page (private, works for one hour):', 'bold');
        $output->line($url !== '' ? '  ' . rtrim($url, '/') . $path : '  ' . $path . '   (add it to your website\'s address)');
        if ($this->reports->latest() === null) {
            $output->line('The doctor hasn\'t run yet, so the page has no report to show. Run ' . Hammer::command('doctor') . ' first.', 'yellow');
        }

        return 0;
    }
}
