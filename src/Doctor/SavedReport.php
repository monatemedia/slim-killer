<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use DateTimeImmutable;

/** A report as the doctor last saved it, and when. */
final readonly class SavedReport
{
    public function __construct(
        public Report $report,
        public DateTimeImmutable $checkedAt,
    ) {}
}
