<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

/**
 * A check of the server as a whole (folders, network, cron). Runs once, from the
 * command line.
 */
interface HostCheck
{
    /** @return list<CheckResult> */
    public function check(): array;
}
