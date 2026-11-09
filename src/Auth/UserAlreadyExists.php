<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use RuntimeException;

final class UserAlreadyExists extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("There is already a user with the email address {$email}.");
    }
}
