<?php

declare(strict_types=1);

/**
 * Example 01 — The aggregate root as the only door
 * -------------------------------------------------------------------------
 *   php examples/01-the-aggregate-root.php
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';
require __DIR__ . '/../src/ApplicationId.php';
require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/IncomeSource.php';
require __DIR__ . '/../src/BondApplication.php';

use Bond\Model\BondApplication;
use Bond\Model\IncomeSource;
use Bond\ValueObject\Currency;
use Bond\ValueObject\Money;

echo "=== Example 01 — The Aggregate Root ===\n\n";

// Start an application requesting R1,250,000.
$app = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
echo "New application: {$app->requestedAmount()->format()} ({$app->status()->value})\n\n";

// Add income — ONLY through the root. Each call passes the boundary's invariants.
$app->addIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
$app->addIncomeSource(new IncomeSource('Weekend Consulting', Money::fromMajorUnits(7_500, Currency::ZAR)));
echo "Income sources added: {$app->incomeSourceCount()}\n";
echo "Total monthly income: {$app->totalMonthlyIncome()->format()}\n\n";

// Invariant 1: income currency must match the bond currency.
try {
    $app->addIncomeSource(new IncomeSource('Offshore Ltd', Money::fromMajorUnits(2_000, Currency::USD)));
} catch (DomainException $e) {
    echo "Rejected (currency):   {$e->getMessage()}\n";
}

// Invariant 2: cannot submit with no income (demonstrated on a fresh application).
try {
    BondApplication::start(Money::fromMajorUnits(500_000, Currency::ZAR))->submit();
} catch (DomainException $e) {
    echo "Rejected (no income):  {$e->getMessage()}\n";
}

// Legal submission.
$app->submit();
echo "\nAfter submit():        {$app->status()->value}\n";

// Invariant 3: the boundary closes after submission — no more edits.
try {
    $app->addIncomeSource(new IncomeSource('Late Addition', Money::fromMajorUnits(1_000, Currency::ZAR)));
} catch (DomainException $e) {
    echo "Rejected (locked):     {$e->getMessage()}\n";
}
