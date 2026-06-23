<?php

declare(strict_types=1);

/**
 * Example 01 — A rule that belongs to no single object
 * -------------------------------------------------------------------------
 *   php examples/01-why-a-domain-service.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Service\AffordabilityService;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;

echo "=== Example 01 — Why a Domain Service ===\n\n";

$rate = Percentage::fromPercent(11.5);
$term = 240; // 20-year bond

$service = new AffordabilityService(); // default policy: instalment <= 30% of income

// Applicant A — comfortable income.
$a = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
$a->addIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
$a->addIncomeSource(new IncomeSource('Side gig', Money::fromMajorUnits(7_500, Currency::ZAR)));

// Applicant B — same bond, thinner income.
$b = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
$b->addIncomeSource(new IncomeSource('Retail job', Money::fromMajorUnits(30_000, Currency::ZAR)));

foreach (['A' => $a, 'B' => $b] as $label => $app) {
    $assessment = $service->assess($app, $rate, $term);
    echo "Applicant {$label}:\n";
    echo "  bond requested:    {$app->requestedAmount()->format()} over {$term} months @ {$rate->format()}\n";
    echo "  monthly income:    {$assessment->monthlyIncome->format()}\n";
    echo "  monthly instalment:{$assessment->requiredInstalment->format()}\n";
    echo "  max @ 30% income:  {$assessment->maxAllowedInstalment->format()}\n";
    echo "  affordable?        " . ($assessment->isAffordable() ? 'YES' : 'NO') . "\n\n";
}

echo "The verdict needs INCOME (the aggregate's line items) + INSTALMENT (amount + terms)\n";
echo "+ a POLICY (30%). No single entity owns all three — so the rule lives in a service.\n";
