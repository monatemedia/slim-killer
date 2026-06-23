<?php

declare(strict_types=1);

namespace Bond\Domain\ValueObject;

use InvalidArgumentException;

/** The Percentage value object from Lesson 1.2 (basis points; applies to Money). */
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

    public static function fromPercent(int|float $percent): self
    {
        return new self((int) round($percent * 100));
    }

    public static function fromBasisPoints(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    /** Apply this percentage to Money, returning Money (rounded to the nearest cent). */
    public function applyTo(Money $money): Money
    {
        return new Money((int) round($money->cents * $this->basisPoints / 10000), $money->currency);
    }

    public function toFloat(): float
    {
        return $this->basisPoints / 100;
    }

    public function format(): string
    {
        return rtrim(rtrim(number_format($this->toFloat(), 2), '0'), '.') . '%';
    }
}
