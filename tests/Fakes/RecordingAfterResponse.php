<?php

declare(strict_types=1);

namespace Tests\Fakes;

use SlimKiller\Support\AfterResponse;

/** Keeps the work instead of running it at shutdown, so a test can see it was asked for. */
final class RecordingAfterResponse implements AfterResponse
{
    /** @var list<callable(): mixed> */
    public array $work = [];

    public function run(callable $work): void
    {
        $this->work[] = $work;
    }
}
