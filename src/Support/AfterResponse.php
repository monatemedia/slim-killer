<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Work to do after the response has gone, so the caller is not kept waiting: the
 * webhook answers GitHub at once, then fetches the build.
 */
interface AfterResponse
{
    /** @param callable(): mixed $work */
    public function run(callable $work): void;
}
