<?php

declare(strict_types=1);

/**
 * Example 04 — Structural equality vs object identity
 * -------------------------------------------------------------------------
 * The defining trait that separates a Value Object from an Entity (Lesson 1.3):
 * a Value Object has NO identity. Two Moneys of the same amount ARE the same money.
 *
 *   php examples/04-equality-vs-identity.php
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';

use Bond\ValueObject\Currency;
use Bond\ValueObject\Money;

echo "=== Example 04 — Equality vs Identity ===\n\n";

$a = Money::fromMajorUnits(50_000, Currency::ZAR);
$b = Money::fromMajorUnits(50_000, Currency::ZAR);

// Structural equality — equal by VALUE.
echo '$a->equals($b)   : ' . ($a->equals($b) ? 'true  (same value -> same money)' : 'false') . "\n";

// Object identity — these are two different instances in memory.
echo '$a === $b        : ' . ($a === $b ? 'true' : 'false (different instances — and we DO NOT care)') . "\n\n";

// Different amount -> not equal.
$c = Money::fromMajorUnits(50_001, Currency::ZAR);
echo '$a->equals($c)   : ' . ($a->equals($c) ? 'true' : 'false (R50,000.00 != R50,001.00)') . "\n";

// Different currency, same number -> not equal.
$d = Money::fromMajorUnits(50_000, Currency::USD);
echo '$a->equals($d)   : ' . ($a->equals($d) ? 'true' : 'false (ZAR != USD)') . "\n\n";

echo "KEY INSIGHT: For Money we ask \"is it equal?\", never \"is it the same object?\".\n";
echo "A BondApplication is the opposite — Lesson 1.3 gives it an identity that\n";
echo "persists even as its attributes change.\n";
