<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/** Identity value object from Lesson 1.3 — a domain-minted UUID. */
final readonly class ApplicationId
{
    public function __construct(
        public string $value,
    ) {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException("ApplicationId must be a UUID; received '{$value}'.");
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
