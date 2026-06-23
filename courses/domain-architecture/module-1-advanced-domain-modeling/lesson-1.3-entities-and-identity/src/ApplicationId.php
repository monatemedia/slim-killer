<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/**
 * ApplicationId — identity modelled as a Value Object.
 *
 * An entity needs an identity that is STABLE over its whole life. We do NOT let a raw
 * database auto-increment leak into the domain — the domain mints its own identity (a
 * UUID), independent of any storage technology. This is itself a value object: it is
 * immutable, self-validating, and compared by value.
 */
final readonly class ApplicationId
{
    public function __construct(
        public string $value,
    ) {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException("ApplicationId must be a UUID; received '{$value}'.");
        }
    }

    /** Mint a fresh identity inside the domain — no database round-trip required. */
    public static function generate(): self
    {
        return new self(self::uuidV4());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
