<?php

declare(strict_types=1);

namespace Tests\Fakes;

use DateTimeImmutable;
use SlimKiller\Support\Clock;

/** A clock that stands still until a test moves it on. */
final class SteppingClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $time)
    {
        $this->now = new DateTimeImmutable($time);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}
