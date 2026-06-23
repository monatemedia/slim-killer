<?php

declare(strict_types=1);

/**
 * Example 02 — You cannot reach past the root into the collection
 * -------------------------------------------------------------------------
 *   php examples/02-protecting-the-collection.php
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

echo "=== Example 02 — Protecting the Collection ===\n\n";

$app = BondApplication::start(Money::fromMajorUnits(900_000, Currency::ZAR));
$app->addIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
echo "Income sources on the aggregate: {$app->incomeSourceCount()}\n\n";

// Grab the collection and try to corrupt it from outside.
$leaked = $app->incomeSources();
$leaked[] = new IncomeSource('Injected Fraud', Money::fromMajorUnits(999_999, Currency::ZAR));
echo "We appended to the RETURNED array. Its length is now: " . count($leaked) . "\n";
echo "But the aggregate is untouched:                       {$app->incomeSourceCount()}\n\n";

echo "Why? PHP arrays are value types — incomeSources() handed back a COPY.\n";
echo "And each IncomeSource is immutable (readonly), so even the items can't be edited.\n";
echo "The ONLY way to change the set is the root's add/remove methods, which guard invariants.\n\n";

// Removal also goes through the root.
$app->removeIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
echo "After removeIncomeSource() via the root: {$app->incomeSourceCount()}\n";
