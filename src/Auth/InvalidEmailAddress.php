<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use InvalidArgumentException;

final class InvalidEmailAddress extends InvalidArgumentException
{
    public function __construct(public readonly string $given)
    {
        parent::__construct("\"{$given}\" is not an email address.");
    }
}
