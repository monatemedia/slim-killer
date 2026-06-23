<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the rich BondApplication challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/BondApplication.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 *
 * Tests assert BEHAVIOUR (the state machine), never layout.
 */

require __DIR__ . '/../src/ApplicationStatus.php';

$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/BondApplication.php'
    : __DIR__ . '/BondApplication.php';

use Bond\Model\ApplicationStatus;
use Bond\Model\BondApplication;

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

/** Assert $fn throws an exception of exactly $class (subclasses count). */
function assertThrows(string $class, callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (\Throwable $e) {
        return $e instanceof $class;
    }
}

function fresh(): BondApplication
{
    return new BondApplication(1_000_000_00);
}

echo "Verifying BondApplication (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('Starts as Draft', fn () => fresh()->status() === ApplicationStatus::Draft);

check('submit(): Draft -> Submitted', function () {
    $a = fresh();
    $a->submit();
    return $a->status() === ApplicationStatus::Submitted;
});

check('approve(): Submitted -> Approved', function () {
    $a = fresh();
    $a->submit();
    $a->approve();
    return $a->status() === ApplicationStatus::Approved;
});

check('approve() on a Draft throws DomainException', fn () => assertThrows(
    \DomainException::class,
    fn () => fresh()->approve(),
));

check('decline(reason): Submitted -> Declined and stores reason', function () {
    $a = fresh();
    $a->submit();
    $a->decline('Affordability check failed.');
    return $a->status() === ApplicationStatus::Declined
        && $a->declineReason() === 'Affordability check failed.';
});

check('decline() on a Draft throws DomainException', fn () => assertThrows(
    \DomainException::class,
    fn () => fresh()->decline('whatever'),
));

check('decline() with a blank reason throws InvalidArgumentException', fn () => assertThrows(
    \InvalidArgumentException::class,
    function () {
        $a = fresh();
        $a->submit();
        $a->decline('   ');
    },
));

echo "\n" . str_repeat('-', 48) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
