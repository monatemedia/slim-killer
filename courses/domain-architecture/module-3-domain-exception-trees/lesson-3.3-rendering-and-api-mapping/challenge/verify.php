<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the ProblemDetailsMapper challenge (framework-free).
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/ProblemDetailsMapper.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 */

require __DIR__ . '/../autoload.php';

// Load the implementation under test BEFORE its class name is referenced (same FQCN as src).
$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/ProblemDetailsMapper.php'
    : __DIR__ . '/ProblemDetailsMapper.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\ApplicationNotFoundException;
use Bond\Domain\Exception\BondAmountExceedsPropertyValueException;
use Bond\Http\Problem\ProblemDetailsMapper;

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

echo "Verifying ProblemDetailsMapper (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('insufficient income -> 422 with type, detail and context', function () use ($appId) {
    $p = ProblemDetailsMapper::map(new ApplicantHasInsufficientIncomeException($appId, 3_000_000, 1_333_038));
    return $p->status === 422
        && $p->body['type'] === '/problems/applicant-has-insufficient-income'
        && $p->body['detail'] === 'Applicant income does not support the required instalment.'
        && ($p->body['context']['monthly_income_cents'] ?? null) === 3_000_000;
});

check('over-leverage -> 422 with context', function () use ($appId) {
    $p = ProblemDetailsMapper::map(new BondAmountExceedsPropertyValueException($appId, 150_000_000, 120_000_000));
    return $p->status === 422 && ($p->body['context']['property_value_cents'] ?? null) === 120_000_000;
});

check('already submitted -> 409', function () use ($appId) {
    return ProblemDetailsMapper::map(new ApplicationAlreadySubmittedException($appId, 'submitted'))->status === 409;
});

check('not found -> 404', function () use ($appId) {
    return ProblemDetailsMapper::map(new ApplicationNotFoundException($appId))->status === 404;
});

check('unexpected throwable -> 500 generic with a reference', function () {
    $p = ProblemDetailsMapper::map(new RuntimeException('host=10.0.0.5 password=hunter2'));
    return $p->status === 500
        && $p->body['title'] === 'Something went wrong'
        && str_starts_with($p->body['reference'] ?? '', 'err_');
});

check('500 body LEAKS NOTHING (no detail/context, no secret in the body)', function () {
    $p = ProblemDetailsMapper::map(new RuntimeException('host=10.0.0.5 password=hunter2'));
    $json = json_encode($p->body);
    return ! isset($p->body['detail'])
        && ! isset($p->body['context'])
        && ! str_contains($json, 'hunter2');
});

check('500 includes a debug block ONLY when debug=true', function () {
    $prod = ProblemDetailsMapper::map(new RuntimeException('boom'), debug: false);
    $dev  = ProblemDetailsMapper::map(new RuntimeException('boom'), debug: true);
    return ! isset($prod->body['debug']) && isset($dev->body['debug']);
});

echo "\n" . str_repeat('-', 60) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
