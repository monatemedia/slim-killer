<?php

declare(strict_types=1);

namespace Bond\ValueObject;

use InvalidArgumentException;

/**
 * ✅ REFERENCE SOLUTION — LoanToValueRatio
 *
 * Note how this Value Object holds NO numbers of its own — it composes two Money
 * objects and DERIVES a Percentage. All the hard guarantees (non-negative money,
 * exact integer maths) are inherited for free from the objects it is built from.
 * That is the compounding payoff of value objects: each new one stands on the last.
 */
final readonly class LoanToValueRatio
{
    public function __construct(
        public Money $loanAmount,
        public Money $propertyValue,
    ) {
        // 3. Cannot divide by a non-positive property value.
        if ($this->propertyValue->cents <= 0) {
            throw new InvalidArgumentException('Property value must be positive.');
        }

        // 4. The two amounts must be in the same currency to form a ratio.
        if ($this->loanAmount->currency !== $this->propertyValue->currency) {
            throw new InvalidArgumentException(
                'Loan and property value must share a currency to compute LTV.'
            );
        }

        // 5. An LTV above 100% is illegal here — over-leverage is rejected up front.
        if ($this->loanAmount->isGreaterThan($this->propertyValue)) {
            throw new InvalidArgumentException(
                'Loan amount cannot exceed property value (LTV would exceed 100%).'
            );
        }
    }

    public function asPercentage(): Percentage
    {
        $basisPoints = (int) round(
            $this->loanAmount->cents / $this->propertyValue->cents * 10000
        );

        return Percentage::fromBasisPoints($basisPoints);
    }
}
