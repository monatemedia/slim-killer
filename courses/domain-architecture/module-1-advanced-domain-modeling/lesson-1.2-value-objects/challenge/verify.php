<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the LoanToValueRatio challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/LoanToValueRatio.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 *
 * Tests assert BEHAVIOUR (what the object does), never layout — per the course's
 * Golden Rule "Test Behaviours, Not Layouts".
 */

require __DIR__ . '/../src/Currency.php';
require __DIR__ . '/../src/Money.php';
require __DIR__ . '/../src/Percentage.php';

$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/LoanToValueRatio.php'
    : __DIR__ . '/LoanToValueRatio.php';

use Bond\ValueObject\Currency;
use Bond\ValueObject\LoanToValueRatio;
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
    if ($ok) {
        $pass++;
        echo "  \e[32m[PASS]\e[0m {$label}\n";
    } else {
        $fail++;
        echo "  \e[31m[FAIL]\e[0m {$label}\n";
    }
}

/** Assert that running $fn throws (used for the rejection rules). */
function throws(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (\InvalidArgumentException) {
        return true;
    }
}

$zar = Currency::ZAR;

echo "Verifying LoanToValueRatio (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('R900k loan on R1m property -> 90%', function () use ($zar) {
    $ltv = new LoanToValueRatio(
        Money::fromMajorUnits(900_000, $zar),
        Money::fromMajorUnits(1_000_000, $zar),
    );
    return $ltv->asPercentage()->equals(\Bond\ValueObject\Percentage::fromPercent(90));
});

check('R1m loan on R1m property -> 100%', function () use ($zar) {
    $ltv = new LoanToValueRatio(
        Money::fromMajorUnits(1_000_000, $zar),
        Money::fromMajorUnits(1_000_000, $zar),
    );
    return $ltv->asPercentage()->equals(\Bond\ValueObject\Percentage::fromPercent(100));
});

check('Rejects property value of zero', fn () => throws(fn () => new LoanToValueRatio(
    Money::fromMajorUnits(100_000, $zar),
    Money::zero($zar),
)));

check('Rejects currency mismatch (ZAR loan, USD property)', fn () => throws(fn () => new LoanToValueRatio(
    Money::fromMajorUnits(900_000, Currency::ZAR),
    Money::fromMajorUnits(1_000_000, Currency::USD),
)));

check('Rejects loan greater than property value (LTV > 100%)', fn () => throws(fn () => new LoanToValueRatio(
    Money::fromMajorUnits(1_100_000, $zar),
    Money::fromMajorUnits(1_000_000, $zar),
)));

echo "\n" . str_repeat('-', 48) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
