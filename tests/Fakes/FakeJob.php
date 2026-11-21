<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use DateTimeImmutable;
use SlimKiller\Cron\CronJob;

/** A cron job that counts its runs, and does what a test tells it to. */
final class FakeJob implements CronJob
{
    public int $runs = 0;

    /** @param (Closure(DateTimeImmutable): string)|null $work */
    public function __construct(
        private readonly string $name,
        private readonly int $every = 1,
        private readonly ?Closure $work = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function everyMinutes(): int
    {
        return $this->every;
    }

    public function run(DateTimeImmutable $now): string
    {
        $this->runs++;

        return $this->work === null ? "Did {$this->name}." : ($this->work)($now);
    }
}
