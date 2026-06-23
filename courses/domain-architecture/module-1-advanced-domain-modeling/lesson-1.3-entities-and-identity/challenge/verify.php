<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the identity-equality challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/BondApplication.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 *
 * Tests assert BEHAVIOUR (identity semantics), never layout.
 */

require __DIR__ . '/../src/ApplicationId.php';
require __DIR__ . '/../src/ApplicationStatus.php';

$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/BondApplication.php'
    : __DIR__ . '/BondApplication.php';

use Bond\Model\BondApplication;
use Bond\ValueObject\ApplicationId;

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

echo "Verifying BondApplication identity (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('Two separately started applications are NOT equal', function () {
    return ! BondApplication::start()->equals(BondApplication::start());
});

check('An application equals itself', function () {
    $a = BondApplication::start();
    return $a->equals($a);
});

check('Equals a reconstructed copy with the SAME id', function () {
    $a = BondApplication::start();
    $reloaded = new BondApplication($a->id());
    return $a->equals($reloaded);
});

check('Identity equality ignores attribute (status) changes', function () {
    $a = BondApplication::start();
    $reloaded = new BondApplication($a->id());
    $a->approve(); // change an attribute on one copy only
    return $a->equals($reloaded); // still the same application
});

check('Different ids are never equal even with identical status', function () {
    $a = new BondApplication(new ApplicationId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'));
    $b = new BondApplication(new ApplicationId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));
    return ! $a->equals($b);
});

echo "\n" . str_repeat('-', 48) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
