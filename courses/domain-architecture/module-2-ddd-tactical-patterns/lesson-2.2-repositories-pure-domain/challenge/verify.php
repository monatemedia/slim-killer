<?php

declare(strict_types=1);

/**
 * Repository CONTRACT verifier.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/InMemory...Repository.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 *
 * The same behavioural contract that any BondApplicationRepository must satisfy. As a
 * bonus it ALSO runs the contract against the real SqliteBondApplicationRepository from
 * src/, proving both implementations are interchangeable behind the interface.
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\ApplicationId;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Infrastructure\Persistence\SqliteBondApplicationRepository;

// Load the implementation under test (student stub or reference solution).
$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/InMemoryBondApplicationRepository.php'
    : __DIR__ . '/InMemoryBondApplicationRepository.php';

use Bond\Infrastructure\Persistence\InMemoryBondApplicationRepository;

$pass = 0;
$fail = 0;

function check(string $label, callable $test): void
{
    global $pass, $fail;
    try {
        $ok = $test();
    } catch (\Throwable $e) {
        $ok = false;
        $label .= "  [threw: " . $e->getMessage() . "]";
    }
    echo $ok ? "  \e[32m[PASS]\e[0m {$label}\n" : "  \e[31m[FAIL]\e[0m {$label}\n";
    $ok ? $pass++ : $fail++;
}

function submittedApp(): BondApplication
{
    $app = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
    $app->addIncomeSource(new IncomeSource('Acme Mining', Money::fromMajorUnits(38_000, Currency::ZAR)));
    $app->addIncomeSource(new IncomeSource('Side gig', Money::fromMajorUnits(7_500, Currency::ZAR)));
    $app->submit();

    return $app;
}

/** The reusable contract: every repository implementation must pass all of these. */
function runContract(string $name, BondApplicationRepository $repo): void
{
    echo "Contract against {$name}:\n";

    check('ofId(unknown) returns null', fn () => $repo->ofId(ApplicationId::generate()) === null);

    check('save() then ofId() returns the same identity', function () use ($repo) {
        $app = submittedApp();
        $repo->save($app);
        $loaded = $repo->ofId($app->id());
        return $loaded !== null && $loaded->equals($app);
    });

    check('round trip preserves amount, status and income', function () use ($repo) {
        $app = submittedApp();
        $repo->save($app);
        $loaded = $repo->ofId($app->id());
        return $loaded->requestedAmount()->equals(Money::fromMajorUnits(1_250_000, Currency::ZAR))
            && $loaded->status() === \Bond\Domain\Model\ApplicationStatus::Submitted
            && $loaded->incomeSourceCount() === 2
            && $loaded->totalMonthlyIncome()->equals(Money::fromMajorUnits(45_500, Currency::ZAR));
    });

    check('snapshot: mutating the saved aggregate does not change stored state', function () use ($repo) {
        // Save a fresh DRAFT, then mutate the original after saving.
        $app = BondApplication::start(Money::fromMajorUnits(800_000, Currency::ZAR));
        $repo->save($app);
        $app->addIncomeSource(new IncomeSource('Late', Money::fromMajorUnits(5_000, Currency::ZAR)));

        $loaded = $repo->ofId($app->id());
        return $loaded->incomeSourceCount() === 0; // the stored snapshot had no income
    });

    echo "\n";
}

echo "=== Repository Contract Verifier ===\n\n";

// The implementation being graded.
runContract(
    ($useSolution ? 'solution ' : 'your ') . 'InMemoryBondApplicationRepository',
    new InMemoryBondApplicationRepository(),
);

// Parity: the real SQLite repo must satisfy the identical contract.
$pdo = new PDO('sqlite::memory:');
SqliteBondApplicationRepository::migrate($pdo);
runContract('SqliteBondApplicationRepository (reference parity)', new SqliteBondApplicationRepository($pdo));

echo str_repeat('-', 60) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CONTRACT CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
