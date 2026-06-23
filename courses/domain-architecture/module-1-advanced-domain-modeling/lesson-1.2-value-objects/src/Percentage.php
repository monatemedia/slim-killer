<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/**
 * Percentage — an immutable, self-validating Value Object for rates and ratios.
 *
 * Used across the Bond domain for the interest rate (e.g. 11.5%) and the
 * loan-to-value ratio (0%–100%). Like Money, it stores an exact integer internally —
 * BASIS POINTS, where 1% = 100 bps — to avoid float drift, and it refuses to exist
 * outside the valid 0%–100% range.
 */
final readonly class Percentage
{
    private function __construct(
        public int $basisPoints,
    ) {
        if ($basisPoints < 0 || $basisPoints > 10000) {
            throw new InvalidArgumentException(
                "Percentage must be between 0% and 100%; received {$basisPoints} basis points."
            );
        }
    }

    /** 11.5 -> 1150 basis points. */
    public static function fromPercent(int|float $percent): self
    {
        return new self((int) round($percent * 100));
    }

    public static function fromBasisPoints(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    /**
     * Apply this percentage to a Money amount, returning Money (rounded to the nearest cent).
     * e.g. 10% of R100.00 -> R10.00.
     */
    public function applyTo(Money $money): Money
    {
        $cents = (int) round($money->cents * $this->basisPoints / 10000);

        return new Money($cents, $money->currency);
    }

    public function toFloat(): float
    {
        return $this->basisPoints / 100;
    }

    public function equals(self $other): bool
    {
        return $this->basisPoints === $other->basisPoints;
    }

    public function format(): string
    {
        // 1150 bps -> "11.5%", 10000 -> "100%", 0 -> "0%"
        $trimmed = rtrim(rtrim(number_format($this->toFloat(), 2), '0'), '.');

        return $trimmed . '%';
    }
}
