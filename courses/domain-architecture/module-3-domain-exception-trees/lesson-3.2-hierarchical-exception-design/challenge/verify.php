<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the exception-tree leaf challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/BondAmount...Exception.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 */

require __DIR__ . '/../autoload.php';

// Load the implementation under test BEFORE its class name is referenced (same FQCN as src).
$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/BondAmountExceedsPropertyValueException.php'
    : __DIR__ . '/BondAmountExceedsPropertyValueException.php';

use Bond\Domain\Exception\BondAmountExceedsPropertyValueException;
use Bond\Domain\Exception\BondApplicationException;
use Bond\Shared\Exception\DomainException;

$pass = 0;
$fail = 0;

function check(string $label, callable $test): void
{
    global $pass, $fail;
    try {
        $ok = $test();
    } catch (\Throwable $e) {
        $ok = false;
        $label .= "  [threw: " . $e::class . ": " . $e->getMessage() . "]";
    }
    echo $ok ? "  \e[32m[PASS]\e[0m {$label}\n" : "  \e[31m[FAIL]\e[0m {$label}\n";
    $ok ? $pass++ : $fail++;
}

$appId = '11111111-1111-4111-8111-111111111111';
$make = fn () => new BondAmountExceedsPropertyValueException($appId, 150_000_000, 120_000_000);

echo "Verifying BondAmountExceedsPropertyValueException (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('extends BondApplicationException (and DomainException, Throwable)', function () use ($make) {
    $e = $make();
    return $e instanceof BondApplicationException
        && $e instanceof DomainException
        && $e instanceof \Throwable;
});

check('has the exact safe business message', function () use ($make) {
    return $make()->getMessage() === 'The requested bond exceeds the value of the property.';
});

check('context() returns exactly the expected keys + values', function () use ($make) {
    return $make()->context() === [
        'application_id'       => '11111111-1111-4111-8111-111111111111',
        'bond_amount_cents'    => 150_000_000,
        'property_value_cents' => 120_000_000,
    ];
});

check('context() leaks nothing (scalars only, no trace/sql/file keys)', function () use ($make) {
    $ctx = $make()->context();
    $forbidden = ['trace', 'sql', 'file', 'line', 'previous', 'message'];
    foreach ($ctx as $key => $value) {
        if (in_array($key, $forbidden, true) || ! is_scalar($value)) {
            return false;
        }
    }
    return true;
});

check('is caught by a catch (BondApplicationException) handler', function () use ($make) {
    try {
        throw $make();
    } catch (BondApplicationException) {
        return true;
    }
});

echo "\n" . str_repeat('-', 56) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
