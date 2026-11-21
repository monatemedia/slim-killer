<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

/** What one job did in one run of cron. */
final readonly class JobReport
{
    public function __construct(
        public string $job,
        public JobOutcome $outcome,
        public string $message,
    ) {}
}
