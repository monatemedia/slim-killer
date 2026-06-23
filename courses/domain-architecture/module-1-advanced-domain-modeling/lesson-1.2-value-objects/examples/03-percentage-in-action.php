<?php

declare(strict_types=1);

/**
 * Example 03 — Percentage applied to Money
 * -------------------------------------------------------------------------
 *   php examples/03-percentage-in-action.php
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';
require __DIR__ . '/../src/Percentage.php';

use Bond\ValueObject\Currency;
use Bond\ValueObject\Money;
use Bond\ValueObject\Percentage;

echo "=== Example 03 — Percentage Value Object ===\n\n";

$bond         = Money::fromMajorUnits(1_250_000.00, Currency::ZAR);
$interestRate = Percentage::fromPercent(11.5);

echo "Bond amount:          " . $bond->format() . "\n";
echo "Annual interest rate: " . $interestRate->format() . "\n";

// Apply the percentage to Money — returns Money, still exact.
$annualInterest = $interestRate->applyTo($bond);
echo "Annual interest:      " . $annualInterest->format() . "\n\n";

// A 10% deposit, expressed as a Percentage, applied to the bond.
$depositRate = Percentage::fromPercent(10);
echo "10% deposit on bond:  " . $depositRate->applyTo($bond)->format() . "\n\n";

// Self-validation: an impossible rate cannot be constructed.
try {
    Percentage::fromPercent(850);
} catch (InvalidArgumentException $e) {
    echo "Absurd rate rejected: " . $e->getMessage() . "\n";
}
