<?php

declare(strict_types=1);

namespace Bond\Domain\Model;

use Bond\Domain\ValueObject\Money;
use InvalidArgumentException;

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
}
