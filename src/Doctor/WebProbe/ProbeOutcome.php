<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\WebProbe;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;

final readonly class ProbeOutcome
{
    /** @param list<CheckResult> $results web server results (rewrites, folder listings) */
    public function __construct(
        public ?Environment $environment,
        public array $results,
    ) {}
}
