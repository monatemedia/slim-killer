<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\Money;
use InvalidArgumentException;

/**
 * IncomeSource — a "line item" that lives INSIDE the BondApplication aggregate.
 *
 * It is immutable: once created it cannot be edited. That is half of how the boundary
 * is protected — even if external code somehow gets hold of an IncomeSource, it cannot
 * change it. The only way to alter the set of income sources is to go through the
 * aggregate root (add/remove), which enforces the application's invariants.
 */
final readonly class IncomeSource
{
    public function __construct(
        private string $employer,
        private Money $monthlyAmount,
    ) {
        if (trim($employer) === '') {
            throw new InvalidArgumentException('An income source needs an employer / source name.');
        }
    }

    public function employer(): string
    {
        return $this->employer;
    }

    public function monthlyAmount(): Money
    {
        return $this->monthlyAmount;
    }

    public function equals(self $other): bool
    {
        return $this->employer === $other->employer
            && $this->monthlyAmount->equals($other->monthlyAmount);
    }
}
