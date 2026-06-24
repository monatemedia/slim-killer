<?php

declare(strict_types=1);

/**
 * Example 01 — Type -> status -> RFC 7807 body (framework-free)
 * -------------------------------------------------------------------------
 *   php examples/01-problem-details.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\ApplicationNotFoundException;
use Bond\Domain\Exception\BondAmountExceedsPropertyValueException;
use Bond\Http\Problem\ProblemDetailsMapper;

$appId = '11111111-1111-4111-8111-111111111111';

echo "=== Example 01 — Problem Details Mapping ===\n\n";

$failures = [
    new ApplicantHasInsufficientIncomeException($appId, 3_000_000, 1_333_038),
    new BondAmountExceedsPropertyValueException($appId, 150_000_000, 120_000_000),
    new ApplicationAlreadySubmittedException($appId, 'submitted'),
    new ApplicationNotFoundException($appId),
];

foreach ($failures as $e) {
    $problem = ProblemDetailsMapper::map($e);
    echo (new ReflectionClass($e))->getShortName() . " -> HTTP {$problem->status}\n";
    echo $problem->toJson() . "\n\n";
}

// An INFRASTRUCTURE / unexpected failure carrying a secret in its message.
$leaky = new RuntimeException('SQLSTATE[HY000] connect failed: host=10.0.0.5 user=root password=hunter2');

echo "Unexpected RuntimeException (APP_DEBUG=false) -> generic, no leak:\n";
echo ProblemDetailsMapper::map($leaky, debug: false)->toJson() . "\n\n";

echo "Same exception with APP_DEBUG=true -> adds a debug block (dev only, never prod):\n";
echo ProblemDetailsMapper::map($leaky, debug: true)->toJson() . "\n\n";

echo "The 500 body never contains the DSN or password — only a reference. Domain failures\n";
echo "(422/409/404) carry their safe message + context, because the domain wrote them to be shown.\n";
