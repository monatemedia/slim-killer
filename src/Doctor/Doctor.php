<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\WebProbe\WebProbe;
use SlimKiller\Doctor\WebProbe\WebTarget;

/**
 * Runs every check and collects the results into a report:
 *
 *   1. the command line's PHP (the PHP checks)
 *   2. the server (the host checks)
 *   3. the website's PHP and web server, when a website was given (the PHP checks
 *      again, against what the probe file reports)
 */
final class Doctor
{
    /**
     * @param list<PhpCheck> $phpChecks
     * @param list<HostCheck> $hostChecks
     */
    public function __construct(
        private readonly Environment $commandLine,
        private readonly WebProbe $webProbe,
        private readonly array $phpChecks,
        private readonly array $hostChecks,
    ) {}

    public function examine(?WebTarget $website = null): Report
    {
        $sections = [
            $this->phpSection($this->commandLine),
            new Section('Server', $this->hostResults()),
        ];

        if ($website === null) {
            $sections[] = new Section('Website', [CheckResult::warn(
                'Website not checked',
                'run again with --url and --webroot to check the website\'s PHP and web server',
                'Example: php hammer doctor --url=https://example.com --webroot=~/domains/example.com/public_html',
            )]);
        } else {
            $outcome = $this->webProbe->probe($website);
            $results = $outcome->environment === null ? [] : $this->phpResults($outcome->environment);
            $title = $outcome->environment === null
                ? 'Website (' . $website->url . ')'
                : 'Website (' . $website->url . ', PHP ' . $outcome->environment->phpVersion() . ', ' . $outcome->environment->sapi() . ')';
            $sections[] = new Section($title, [...$results, ...$outcome->results]);
        }

        return new Report($sections);
    }

    private function phpSection(Environment $environment): Section
    {
        return new Section(
            $environment->label() . ' (PHP ' . $environment->phpVersion() . ', ' . $environment->sapi() . ')',
            $this->phpResults($environment),
        );
    }

    /** @return list<CheckResult> */
    private function phpResults(Environment $environment): array
    {
        $results = [];
        foreach ($this->phpChecks as $check) {
            array_push($results, ...$check->check($environment));
        }

        return $results;
    }

    /** @return list<CheckResult> */
    private function hostResults(): array
    {
        $results = [];
        foreach ($this->hostChecks as $check) {
            array_push($results, ...$check->check());
        }

        return $results;
    }
}
