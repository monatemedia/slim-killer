<?php

declare(strict_types=1);

/**
 * Example 01 — The exception tree
 * -------------------------------------------------------------------------
 *   php examples/01-the-tree.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\BondAmountExceedsPropertyValueException;
use Bond\Domain\Exception\BondApplicationException;
use Bond\Domain\Rule\BondRules;
use Bond\Shared\Exception\DomainException;

$appId = '11111111-1111-4111-8111-111111111111';

echo "=== Example 01 — The Exception Tree ===\n\n";

echo "Tree:\n";
echo "  DomainException (Shared, abstract root)\n";
echo "  └─ BondApplicationException (bounded-context node, abstract)\n";
echo "     ├─ ApplicantHasInsufficientIncomeException\n";
echo "     ├─ BondAmountExceedsPropertyValueException\n";
echo "     └─ ApplicationAlreadySubmittedException\n\n";

$leaves = [
    new ApplicantHasInsufficientIncomeException($appId, 3_000_000, 1_333_038),
    new BondAmountExceedsPropertyValueException($appId, 150_000_000, 120_000_000),
    new ApplicationAlreadySubmittedException($appId, 'submitted'),
];

echo "Each leaf carries a safe message + structured context (no traces, no SQL):\n";
foreach ($leaves as $leaf) {
    echo "  • " . (new ReflectionClass($leaf))->getShortName() . "\n";
    echo "      message: {$leaf->getMessage()}\n";
    echo "      context: " . json_encode($leaf->context()) . "\n";
}

echo "\nEvery leaf is-a node is-a root is-a Throwable:\n";
$sample = $leaves[0];
echo "  instanceof BondApplicationException : " . ($sample instanceof BondApplicationException ? 'true' : 'false') . "\n";
echo "  instanceof DomainException          : " . ($sample instanceof DomainException ? 'true' : 'false') . "\n";
echo "  instanceof Throwable                : " . ($sample instanceof Throwable ? 'true' : 'false') . "\n\n";

echo "So ONE catch at the node level handles every bond leaf:\n";
foreach ($leaves as $leaf) {
    try {
        throw $leaf;
    } catch (BondApplicationException $e) {
        echo "  caught as BondApplicationException -> " . (new ReflectionClass($e))->getShortName() . "\n";
    }
}

echo "\nLeaves are usually thrown by guards. BondRules uses a `never`-returning helper:\n";
try {
    BondRules::assertBondWithinPropertyValue($appId, 150_000_000, 120_000_000); // bond > property
} catch (BondApplicationException $e) {
    echo "  guard threw -> " . (new ReflectionClass($e))->getShortName() . ": {$e->getMessage()}\n";
}
