<?php

declare(strict_types=1);

/**
 * Example 02 — Composable policies & the typed exception
 * -------------------------------------------------------------------------
 *   php examples/02-composable-policies.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Service\AffordabilityService;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;

echo "=== Example 02 — Composable Policies ===\n\n";

$rate = Percentage::fromPercent(11.5);
$term = 240;

// The borderline applicant from Example 01 (income R30,000).
$app = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
$app->addIncomeSource(new IncomeSource('Retail job', Money::fromMajorUnits(30_000, Currency::ZAR)));

// Three different lending policies, each a callable (Money $income): Money.
$policies = [
    'standard 30%'       => AffordabilityService::standardPolicy(...),                       // first-class callable
    'generous 45%'       => fn (Money $income) => Percentage::fromPercent(45)->applyTo($income),
    'flat R15,000 cap'   => fn (Money $income) => Money::fromMajorUnits(15_000, Currency::ZAR),
];

foreach ($policies as $name => $policy) {
    $verdict = (new AffordabilityService($policy))->assess($app, $rate, $term);
    printf(
        "  %-18s max=%-12s instalment=%-12s -> %s\n",
        $name,
        $verdict->maxAllowedInstalment->format(),
        $verdict->requiredInstalment->format(),
        $verdict->isAffordable() ? 'AFFORDABLE' : 'declined',
    );
}

echo "\nSame applicant, same maths — only the injected POLICY changes the verdict.\n\n";

// The "throw a typed exception" path — the bridge to Module 3.
echo "guardAffordable() under the standard policy:\n";
try {
    (new AffordabilityService())->guardAffordable($app, $rate, $term);
    echo "  approved\n";
} catch (ApplicantHasInsufficientIncomeException $e) {
    echo "  threw " . (new ReflectionClass($e))->getShortName() . "\n";
    echo "  message: {$e->getMessage()}\n";
    echo "  carries context -> income={$e->monthlyIncome->format()} instalment={$e->requiredInstalment->format()}\n";
    echo "  (Module 3 maps THIS typed exception to a clean 422 HTTP response.)\n";
}
