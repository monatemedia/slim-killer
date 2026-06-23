<?php

declare(strict_types=1);

/**
 * Example 02 — PDOException never escapes the boundary
 * -------------------------------------------------------------------------
 *   php examples/02-wrapping-at-the-boundary.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Exception\DomainException;
use Bond\Infrastructure\Exception\PersistenceException;
use Bond\Infrastructure\Persistence\SafeBondApplicationStore;

echo "=== Example 02 — Wrapping at the Boundary ===\n\n";

$pdo = new PDO('sqlite::memory:'); // no schema -> the INSERT will fail
$store = new SafeBondApplicationStore($pdo);

// Try to catch the RAW driver exception outside the store. It must NOT fire.
$leakedPdo = null;
$wrapped = null;
try {
    $store->save('11111111-1111-4111-8111-111111111111');
} catch (PDOException $e) {
    $leakedPdo = $e;        // <- if this ever runs, the abstraction leaked
} catch (PersistenceException $e) {
    $wrapped = $e;          // <- this is what the boundary guarantees instead
}

echo "PDOException escaped the store?   " . ($leakedPdo !== null ? 'YES (leak!)' : 'no') . "\n";
echo "Caught PersistenceException?      " . ($wrapped !== null ? 'yes' : 'no') . "\n";
echo "Original kept for server logs?    "
    . ($wrapped?->getPrevious() instanceof PDOException ? 'yes — PDOException via getPrevious()' : 'no') . "\n\n";

// Category check: which base type does each failure belong to?
echo "Classification (what the edge routes on):\n";
$cases = [
    'PersistenceException (db down)'               => $wrapped,
    'ApplicantHasInsufficientIncomeException'      => new ApplicantHasInsufficientIncomeException(3_000_000, 1_333_038),
];
foreach ($cases as $label => $ex) {
    $isDomain = $ex instanceof DomainException;
    printf("  %-42s %s -> %s\n", $label, $isDomain ? 'DOMAIN' : 'INFRA ', $isDomain ? 'HTTP 422' : 'HTTP 500');
}

echo "\nBecause PersistenceException is NOT a DomainException, it can never be mistaken for a\n";
echo "business rule and rendered to the user as one. The two categories stay separate.\n";
