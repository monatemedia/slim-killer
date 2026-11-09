<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

use SlimKiller\Doctor\Environment\Environment;

/**
 * A check of one PHP runtime. The doctor runs every PhpCheck twice: against the
 * command line's PHP and against the website's PHP, because on shared hosting they are
 * often different versions with different settings.
 */
interface PhpCheck
{
    /** @return list<CheckResult> */
    public function check(Environment $environment): array;
}
