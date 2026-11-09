<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Someone who can use the control panel. A user without a password has a welcome
 * link waiting; a user without two-factor has not finished setting up, and cannot
 * get past the two-factor step.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public Role $role,
        public ?string $passwordHash,
        public ?string $totpSecret,
        public ?int $totpLastCounter,
    ) {}

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->totpSecret !== null;
    }
}
