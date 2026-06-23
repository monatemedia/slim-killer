<?php

declare(strict_types=1);

namespace Bond\Domain\Service;

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;
use Closure;

/**
 * AffordabilityService — a STATELESS domain service.
 *
 * WHY IT EXISTS: the rule "the instalment may not exceed 30% of monthly income" needs
 * three things that live in different places — the applicant's income (the aggregate's
 * line items), the instalment (the requested amount + lending terms), and a policy
 * threshold (a lending rule). It belongs to no single entity or value object, so it gets
 * its own home. That home is a domain service.
 *
 * STATELESS: the only thing stored is the policy (configuration injected once). Every
 * assessment takes all of its inputs as parameters and returns a result — no per-call
 * state is kept on the object (prequel Rule 5: objects either hold state or do work).
 *
 * COMPOSABLE POLICY: the affordability threshold is a callable `(Money $income): Money`
 * returning the maximum allowable instalment. Swap it to change lending rules without
 * touching the service.
 */
final class AffordabilityService
{
    /** @var Closure(Money): Money */
    private Closure $maxInstalmentPolicy;

    public function __construct(?callable $maxInstalmentPolicy = null)
    {
        $this->maxInstalmentPolicy = $maxInstalmentPolicy !== null
            ? Closure::fromCallable($maxInstalmentPolicy)
            : self::standardPolicy(...); // PHP 8.1 first-class callable syntax
    }

    /** The "return a typed result" path. */
    public function assess(BondApplication $application, Percentage $annualRate, int $termMonths): AffordabilityAssessment
    {
        $income        = $application->totalMonthlyIncome();
        $instalment    = $application->estimatedMonthlyInstalment($annualRate, $termMonths);
        $maxInstalment = ($this->maxInstalmentPolicy)($income);

        return new AffordabilityAssessment($income, $instalment, $maxInstalment);
    }

    /** The "throw a typed exception" path — the bridge to Module 3. */
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

    /** Default lending policy: the instalment may not exceed 30% of monthly income. */
    public static function standardPolicy(Money $income): Money
    {
        return Percentage::fromPercent(30)->applyTo($income);
    }
}
