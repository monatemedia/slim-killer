<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Length is what makes a password strong, so the only rule is length. No forced
 * symbols or digits, which mostly produce "Password1!".
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 12;

    public const int MAX_LENGTH = 200;

    /** What is wrong with the password, in plain words, or null if nothing is. */
    public static function problem(string $password, string $confirmation): ?string
    {
        $length = mb_strlen($password);

        return match (true) {
            $length < self::MIN_LENGTH => 'Use at least ' . self::MIN_LENGTH . ' characters. A few words in a row works well.',
            $length > self::MAX_LENGTH => 'Use at most ' . self::MAX_LENGTH . ' characters.',
            $password !== $confirmation => 'The two passwords are not the same.',
            default => null,
        };
    }
}
