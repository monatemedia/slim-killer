<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Has a password turned up in a data breach? Attackers try those first, whatever their
 * length, so a password found in one is refused (WelcomeAction).
 */
interface BreachedPasswords
{
    /** How many times the password was seen in breaches: 0 if never, null if the check could not be made. */
    public function timesSeen(string $password): ?int;
}
