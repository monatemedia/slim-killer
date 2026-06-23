<?php

declare(strict_types=1);

namespace Bond\Domain\Service;

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;
use Closure;

/** ✅ REFERENCE SOLUTION — the affordability domain service. */
final class AffordabilityService
{
    /** @var Closure(Money): Money */
    private Closure $maxInstalmentPolicy;

    public function __construct(?callable $maxInstalmentPolicy = null)
    {
        $this->maxInstalmentPolicy = $maxInstalmentPolicy !== null
            ? Closure::fromCallable($maxInstalmentPolicy)
            : self::standardPolicy(...);
    }

    public function assess(BondApplication $application, Percentage $annualRate, int $termMonths): AffordabilityAssessment
    {
        $income        = $application->totalMonthlyIncome();
        $instalment    = $application->estimatedMonthlyInstalment($annualRate, $termMonths);
        $maxInstalment = ($this->maxInstalmentPolicy)($income);

        return new AffordabilityAssessment($income, $instalment, $maxInstalment);
    }

    public function guardAffordable(BondApplication $application, Percentage $annualRate, int $termMonths): void
    {
        $assessment = $this->assess($application, $annualRate, $termMonths);

        if (! $assessment->isAffordable()) {
            throw new ApplicantHasInsufficientIncomeException(
                $application->id(),
                $assessment->monthlyIncome,
                $assessment->requiredInstalment,
            );
        }
    }

    public static function standardPolicy(Money $income): Money
    {
        return Percentage::fromPercent(30)->applyTo($income);
    }
}
