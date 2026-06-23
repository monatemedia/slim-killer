<?php

declare(strict_types=1);

/**
 * Example 01 — Save and reconstitute an aggregate through the interface
 * -------------------------------------------------------------------------
 *   php examples/01-repository-roundtrip.php
 *
 * The code below depends ONLY on the BondApplicationRepository interface. It never
 * mentions an array, a column, or SQL — it hands over an aggregate and gets an
 * aggregate back.
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Infrastructure\Persistence\InMemoryBondApplicationRepository;

echo "=== Example 01 — Repository Round-Trip ===\n\n";

// An "application service" that depends on the INTERFACE (constructor injection / DIP).
$persist = static function (BondApplicationRepository $repo): string {
    $app = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
    $app->addIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
    $app->addIncomeSource(new IncomeSource('Side gig', Money::fromMajorUnits(7_500, Currency::ZAR)));
    $app->submit();

    $repo->save($app);
    return $app->id()->value;
};

$repo = new InMemoryBondApplicationRepository();
$id   = $persist($repo);

echo "Saved application id: {$id}\n\n";

// Reconstitute it — a fresh aggregate object carrying the same data.
$loaded = $repo->ofId(new \Bond\Domain\ValueObject\ApplicationId($id));

echo "Reconstituted from the repository:\n";
echo "  requested amount:   {$loaded->requestedAmount()->format()}\n";
echo "  status:             {$loaded->status()->value}\n";
echo "  income sources:     {$loaded->incomeSourceCount()}\n";
echo "  total income:       {$loaded->totalMonthlyIncome()->format()}\n\n";

// Unknown id -> null (the contract every implementation must honour).
$missing = $repo->ofId(\Bond\Domain\ValueObject\ApplicationId::generate());
echo "ofId(unknown):        " . ($missing === null ? 'null' : 'unexpected!') . "\n";
