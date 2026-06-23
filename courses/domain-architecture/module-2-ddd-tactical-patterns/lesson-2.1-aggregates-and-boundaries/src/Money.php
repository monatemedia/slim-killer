<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/**
 * Money — the immutable, self-validating value object from Lesson 1.2.
 * Reused here so the aggregate can total income and enforce money invariants.
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public Currency $currency,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException("Money cannot be negative; received {$cents} cents.");
        }
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromMajorUnits(int|float $amount, Currency $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function hasSameCurrencyAs(self $other): bool
    {
        return $this->currency === $other->currency;
    }

    public function format(): string
    {
        return sprintf('%s%s', $this->currency->symbol(), number_format($this->cents / 100, 2));
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->hasSameCurrencyAs($other)) {
            throw new InvalidArgumentException(
                "Currency mismatch: cannot operate on {$this->currency->value} and {$other->currency->value}."
            );
        }
    }
}
