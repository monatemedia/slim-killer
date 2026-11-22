<?php

declare(strict_types=1);

namespace Tests\Fakes;

use SlimKiller\Auth\BreachedPasswords;

/** Answers every password the same way, so tests never ask Have I Been Pwned. */
final class FakeBreachedPasswords implements BreachedPasswords
{
    public function __construct(
        private readonly ?int $timesSeen = 0,
    ) {}

    public function timesSeen(string $password): ?int
    {
        return $this->timesSeen;
    }
}
