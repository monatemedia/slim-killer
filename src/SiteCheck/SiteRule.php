<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck;

use SlimKiller\Doctor\CheckResult;

/** One group of `hammer site:check` checks, shown under its own heading. */
interface SiteRule
{
    public function heading(): string;

    /** @return list<CheckResult> */
    public function check(Site $site): array;
}
