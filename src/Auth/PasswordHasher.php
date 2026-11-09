<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Argon2id where PHP has it, bcrypt otherwise. Both are safe; PHP stores the
 * algorithm in the hash, so a site can move from one to the other without a reset.
 */
final class PasswordHasher
{
    private ?string $dummy = null;

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm());
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm());
    }

    /**
     * Takes as long as a real check, for an email address that has no user, so the
     * response time does not reveal which addresses have accounts.
     */
    public function verifyNothing(string $password): void
    {
        $this->dummy ??= $this->hash(bin2hex(random_bytes(16)));
        password_verify($password, $this->dummy);
    }

    private function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
