<?php

declare(strict_types=1);

namespace Bond\Domain\Service;

use Bond\Domain\ValueObject\Money;

/**
 * AffordabilityAssessment — the TYPED RESULT the service returns.
 *
 * A domain service can express its outcome two ways: return a result object (this), or
 * throw a typed exception when a rule is violated. This value object is the "return a
 * result" path — it reports the figures and the verdict without deciding what the caller
 * should do about it.
 */
final readonly class AffordabilityAssessment
{
    public function __construct(
        public Money $monthlyIncome,
        public Money $requiredInstalment,
        public Money $maxAllowedInstalment,
    ) {}

    public function isAffordable(): bool
    {
        return ! $this->requiredInstalment->isGreaterThan($this->maxAllowedInstalment);
    }
}
