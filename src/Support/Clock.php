<?php

declare(strict_types=1);

namespace SlimKiller\Support;

use DateTimeImmutable;

/**
 * The current time, injected rather than read with time() so code that depends on it
 * can be tested (BUILD_PHILOSOPHY.md, Rule 8).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
