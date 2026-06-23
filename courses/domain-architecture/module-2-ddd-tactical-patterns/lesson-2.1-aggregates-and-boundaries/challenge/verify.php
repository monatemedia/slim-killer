<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the aggregate-boundary challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/BondApplication.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 *
 * Tests assert BEHAVIOUR (the boundary invariants), never layout.
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';
require __DIR__ . '/../src/ApplicationId.php';
require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/IncomeSource.php';

$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/BondApplication.php'
    : __DIR__ . '/BondApplication.php';

use Bond\Model\ApplicationStatus;
use Bond\Model\BondApplication;
use Bond\Model\IncomeSource;
use Bond\ValueObject\Currency;
use Bond\ValueObject\Money;

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

function assertThrows(string $class, callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (\Throwable $e) {
        return $e instanceof $class;
    }
}

function freshApp(): BondApplication
{
    return BondApplication::start(Money::fromMajorUnits(1_000_000, Currency::ZAR));
}

function zarIncome(string $employer, int $rands): IncomeSource
{
    return new IncomeSource($employer, Money::fromMajorUnits($rands, Currency::ZAR));
}

echo "Verifying BondApplication aggregate (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('Fresh application has zero total income (in app currency)', function () {
    return freshApp()->totalMonthlyIncome()->equals(Money::zero(Currency::ZAR));
});

check('Total income sums every source', function () {
    $app = freshApp();
    $app->addIncomeSource(zarIncome('Acme', 38_000));
    $app->addIncomeSource(zarIncome('Side gig', 7_500));
    return $app->totalMonthlyIncome()->equals(Money::fromMajorUnits(45_500, Currency::ZAR));
});

check('addIncomeSource rejects a mismatched currency (DomainException)', fn () => assertThrows(
    \DomainException::class,
    fn () => freshApp()->addIncomeSource(
        new IncomeSource('Offshore', Money::fromMajorUnits(2_000, Currency::USD))
    ),
));

check('submit() with no income throws DomainException', fn () => assertThrows(
    \DomainException::class,
    fn () => freshApp()->submit(),
));

check('submit() with income moves to Submitted', function () {
    $app = freshApp();
    $app->addIncomeSource(zarIncome('Acme', 38_000));
    $app->submit();
    return $app->status() === ApplicationStatus::Submitted;
});

check('addIncomeSource AFTER submit throws DomainException (boundary closed)', fn () => assertThrows(
    \DomainException::class,
    function () {
        $app = freshApp();
        $app->addIncomeSource(zarIncome('Acme', 38_000));
        $app->submit();
        $app->addIncomeSource(zarIncome('Late', 1_000));
    },
));

echo "\n" . str_repeat('-', 56) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
