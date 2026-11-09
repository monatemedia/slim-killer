<?php

declare(strict_types=1);

namespace Tests\Fakes;

use DateTimeImmutable;
use SlimKiller\Support\Clock;

final class FrozenClock implements Clock
{
    public function __construct(
        private readonly DateTimeImmutable $now,
    ) {}

    public static function at(string $time): self
    {
        return new self(new DateTimeImmutable($time));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
