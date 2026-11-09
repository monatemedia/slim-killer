<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

final readonly class Section
{
    /** @param list<CheckResult> $results */
    public function __construct(
        public string $title,
        public array $results,
    ) {}
}
