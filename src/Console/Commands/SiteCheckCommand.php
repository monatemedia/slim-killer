<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Content\ContentLocation;
use SlimKiller\Doctor\ReportPrinter;
use SlimKiller\SiteCheck\Site;
use SlimKiller\SiteCheck\SiteChecker;
use SlimKiller\Support\Hammer;

/**
 * `hammer site:check ../client-site`: checks a client's site against the Astro site
 * rules before it goes live, and in its GitHub workflow after every build. Without a
 * folder, it checks the site CONTENT_DIR points at. Reads files only.
 */
final readonly class SiteCheckCommand implements Command
{
    public function __construct(
        private ContentLocation $location,
        private bool $contentConfigured,
    ) {}

    public function name(): string
    {
        return 'site:check';
    }

    public function description(): string
    {
        return 'Check a client site against the Astro site rules (the site\'s folder, or the one CONTENT_DIR points at)';
    }

    public function run(Input $input, Output $output): int
    {
        $asked = $input->arguments[0] ?? null;
        $folder = $asked ?? ($this->contentConfigured ? dirname($this->location->contentDir) : null);
        if ($folder === null) {
            $output->line('Which site? Give its folder, the one with content/ and site/ in it:', 'red');
            $output->line('  ' . Hammer::command('site:check ../client-site'));

            return 2;
        }

        $root = realpath($folder);
        if ($root === false || (! is_dir($root . '/content') && ! is_dir($root . '/site'))) {
            $output->line("Not a client site: {$folder} has no content/ or site/ folder.", 'red');

            return 2;
        }

        $report = SiteChecker::standard()->check(new Site($root));
        ReportPrinter::print(
            $report,
            $output,
            'Slim Killer site check: ' . basename($root),
            'Follows the Astro site rules.',
            'Not yet. %s. Fix the FAIL lines above and check again.',
        );

        return $report->passed() ? 0 : 1;
    }
}
