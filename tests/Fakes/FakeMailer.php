<?php

declare(strict_types=1);

namespace Tests\Fakes;

use SlimKiller\Mail\Letter;
use SlimKiller\Mail\Mailer;

final class FakeMailer implements Mailer
{
    /** @var list<Letter> */
    public array $sent = [];

    public function __construct(
        private readonly bool $accepts = true,
    ) {}

    public function send(Letter $letter): bool
    {
        if ($this->accepts) {
            $this->sent[] = $letter;
        }

        return $this->accepts;
    }
}
