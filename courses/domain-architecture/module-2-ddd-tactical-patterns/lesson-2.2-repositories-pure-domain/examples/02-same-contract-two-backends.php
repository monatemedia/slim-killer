<?php

declare(strict_types=1);

/**
 * Example 02 — One contract, two backends (array vs real SQLite)
 * -------------------------------------------------------------------------
 *   php examples/02-same-contract-two-backends.php
 *
 * The exact same scenario is run against the in-memory fake AND a real PDO/SQLite
 * database. The scenario code does not change a single character between them — that
 * is the payoff of depending on the interface (Rule D + the Dependency Rule).
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Infrastructure\Persistence\InMemoryBondApplicationRepository;
use Bond\Infrastructure\Persistence\SqliteBondApplicationRepository;

// The scenario is written ONCE, against the interface.
function runScenario(BondApplicationRepository $repo): string
{
    $app = BondApplication::start(Money::fromMajorUnits(2_000_000, Currency::ZAR));
    $app->addIncomeSource(new IncomeSource('Hospital Group', Money::fromMajorUnits(72_000, Currency::ZAR)));
    $app->submit();
    $repo->save($app);

    $loaded = $repo->ofId($app->id());

    return sprintf(
        'amount=%s status=%s income=%s total=%s',
        $loaded->requestedAmount()->format(),
        $loaded->status()->value,
        $loaded->incomeSourceCount(),
        $loaded->totalMonthlyIncome()->format(),
    );
}

echo "=== Example 02 — Same Contract, Two Backends ===\n\n";

// Backend A: in-memory array.
$memory = new InMemoryBondApplicationRepository();
echo "In-memory backend:  " . runScenario($memory) . "\n";

// Backend B: a real SQLite database (in memory for the demo).
$pdo = new PDO('sqlite::memory:');
SqliteBondApplicationRepository::migrate($pdo);
$sqlite = new SqliteBondApplicationRepository($pdo);
echo "SQLite backend:     " . runScenario($sqlite) . "\n\n";

// Prove the SQLite backend really wrote rows (peek BEHIND the interface — infra only).
$rows = $pdo->query('SELECT bond_amount, currency, status FROM applications')->fetchAll(PDO::FETCH_ASSOC);
echo "Raw applications row in SQLite: " . json_encode($rows[0]) . "\n";
echo "We saved Money via toDecimalString() ('2000000.00'); SQLite's NUMERIC affinity\n";
echo "stored it as 2000000. The repository converts both ways — the domain never knows.\n";
