<?php

declare(strict_types=1);

namespace Bond\Domain\Service;

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;
use Closure;

/**
 * 🏗️  CODE CHALLENGE — Implement the affordability domain service
 * -------------------------------------------------------------------------
 * The plumbing (the injectable policy, the default 30% policy) is done. Implement the
 * two methods that make this a real domain service.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. assess(app, annualRate, termMonths): AffordabilityAssessment
 *        - income     = the application's total monthly income
 *        - instalment = the application's estimated monthly instalment for the rate/term
 *        - max        = the injected policy applied to the income
 *        - return all three wrapped in an AffordabilityAssessment.
 *   2. guardAffordable(app, annualRate, termMonths): void
 *        - run assess(); if the result is NOT affordable, throw
 *          ApplicantHasInsufficientIncomeException carrying the application id, the
 *          income, and the required instalment. Otherwise return normally.
 *
 * TIP: BondApplication has ->totalMonthlyIncome() and
 *      ->estimatedMonthlyInstalment($rate, $term). Apply the policy with
 *      ($this->maxInstalmentPolicy)($income).
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/AffordabilityService.php
 */
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
        // TODO: requirement 1
        throw new \RuntimeException('TODO: implement AffordabilityService::assess()');
    }

    public function guardAffordable(BondApplication $application, Percentage $annualRate, int $termMonths): void
    {
        // TODO: requirement 2
        throw new \RuntimeException('TODO: implement AffordabilityService::guardAffordable()');
    }

    public static function standardPolicy(Money $income): Money
    {
        return Percentage::fromPercent(30)->applyTo($income);
    }
}
