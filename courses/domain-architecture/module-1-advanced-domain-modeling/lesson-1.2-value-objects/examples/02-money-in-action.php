<?php

declare(strict_types=1);

/**
 * Example 02 — Money, the cure
 * -------------------------------------------------------------------------
 *   php examples/02-money-in-action.php
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';

use Bond\ValueObject\Currency;
use Bond\ValueObject\Money;

echo "=== Example 02 — Money Value Object ===\n\n";

// Build money the safe way — from major units (rands) or directly from cents.
$bond    = Money::fromMajorUnits(1_250_000.00, Currency::ZAR);
$deposit = Money::fromMajorUnits(125_000.00, Currency::ZAR);

echo "Requested bond:   " . $bond->format() . "\n";
echo "Deposit:          " . $deposit->format() . "\n";

// Exact arithmetic — integer cents, no float drift.
$financed = $bond->subtract($deposit);
echo "Financed amount:  " . $financed->format() . "\n\n";

// 1. Self-validation: a negative Money cannot be constructed.
try {
    new Money(-500, Currency::ZAR);
} catch (InvalidArgumentException $e) {
    echo "1. Negative Money rejected:   " . $e->getMessage() . "\n";
}

// 2. Currency safety: rands and dollars cannot be mixed.
try {
    $bond->add(Money::fromMajorUnits(1000, Currency::USD));
} catch (InvalidArgumentException $e) {
    echo "2. Currency mismatch rejected: " . $e->getMessage() . "\n";
}

// 3. Immutability: "changing" money returns a NEW object; the original is untouched.
$adjusted = $bond->withCents(99_999_900);
echo "3. Original after withCents(): " . $bond->format() . " (unchanged)\n";
echo "   New adjusted Money:         " . $adjusted->format() . "\n\n";

// 4. The 0.1 + 0.2 problem from Example 01 simply cannot happen.
$a = Money::fromMajorUnits(0.1, Currency::ZAR);
$b = Money::fromMajorUnits(0.2, Currency::ZAR);
echo "4. R0.10 + R0.20 =             " . $a->add($b)->format() . " (exactly R0.30)\n";
