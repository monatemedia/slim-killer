<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/**
 * Money — an immutable, self-validating Value Object.
 *
 * THE THREE TRAITS OF A VALUE OBJECT, all visible here:
 *   1. Immutability    → `final readonly class`; every "change" returns a NEW Money.
 *   2. Self-validation  → the constructor throws before a negative/invalid Money can exist.
 *   3. Structural equality → two Moneys are equal by value, never by object identity.
 *
 * WHY INTEGER CENTS, NOT FLOAT:
 *   The current Bond flow stores `(float) $data['bond_amount']`. Floats cannot represent
 *   money exactly — `0.1 + 0.2 !== 0.3`. We store the smallest indivisible unit (cents)
 *   as an int, so arithmetic is exact and rounding happens only at well-defined edges.
 *
 * PHP 8.5 NOTE:
 *   `withCents()` below is a hand-written "wither" so this file runs on PHP 8.3+. On the
 *   course's target runtime (8.5) you would write `return clone $this with ['cents' => $cents];`
 *   and tag it `#[\NoDiscard]`. See ../php85-preview/money-with-clone-with.php.
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public Currency $currency,
    ) {
        // Rule B — make illegal states unrepresentable. A negative bond amount
        // is not "validated later"; it can never be constructed at all.
        if ($cents < 0) {
            throw new InvalidArgumentException(
                "Money cannot be negative; received {$cents} cents."
            );
        }
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Build from a major-unit amount (e.g. rands).
     * 1_250_000.50 rands -> 125_000_050 cents.
     */
    public static function fromMajorUnits(int|float $amount, Currency $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        // The constructor guards the result — subtracting into the negative throws.
        return new self($this->cents - $other->cents, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    /**
     * Structural equality: same amount AND same currency.
     * Two separately-constructed Money(50_000_000, ZAR) are equal. Identity is irrelevant.
     */
    public function equals(self $other): bool
    {
        return $this->cents === $other->cents
            && $this->currency === $other->currency;
    }

    /**
     * Return a NEW Money with a different amount. The original is never mutated.
     * (PHP 8.5 target: `return clone $this with ['cents' => $cents];`)
     */
    public function withCents(int $cents): self
    {
        return new self($cents, $this->currency);
    }

    public function format(): string
    {
        return sprintf(
            '%s%s',
            $this->currency->symbol(),
            number_format($this->cents / 100, 2)
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: cannot operate on {$this->currency->value} and {$other->currency->value}."
            );
        }
    }
}
