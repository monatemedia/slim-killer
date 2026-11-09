<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * An email address, trimmed and lower-cased, so "Edward@Example.com " and
 * "edward@example.com" are the same person (BUILD_PHILOSOPHY.md, Rule 9).
 */
final readonly class EmailAddress
{
    private function __construct(
        public string $value,
    ) {}

    /** @throws InvalidEmailAddress */
    public static function from(string $value): self
    {
        $normalised = strtolower(trim($value));

        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailAddress($value);
        }

        return new self($normalised);
    }
}
