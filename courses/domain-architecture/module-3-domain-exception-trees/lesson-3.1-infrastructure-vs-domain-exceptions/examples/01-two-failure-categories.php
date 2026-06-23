<?php

declare(strict_types=1);

/**
 * Example 01 — Two categories, two destinations
 * -------------------------------------------------------------------------
 * The same edge (ErrorResponder) handles both a business failure and a technical one,
 * and sends them to completely different HTTP outcomes.
 *
 *   php examples/01-two-failure-categories.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Http\ErrorResponder;
use Bond\Infrastructure\Persistence\SafeBondApplicationStore;

echo "=== Example 01 — Two Failure Categories ===\n\n";

// --- A DOMAIN failure: a business rule said no. ---
try {
    throw new ApplicantHasInsufficientIncomeException(3_000_000, 1_333_038);
} catch (\Throwable $e) {
    [$status, $body] = ErrorResponder::render($e);
    echo "Domain failure (insufficient income):\n";
    echo "  -> HTTP {$status} " . json_encode($body) . "\n";
    echo "  The applicant gets a precise, safe reason.\n\n";
}

// --- An INFRASTRUCTURE failure: the database is unusable (table not migrated). ---
$pdo = new PDO('sqlite::memory:'); // deliberately no schema
$store = new SafeBondApplicationStore($pdo);

try {
    $store->save('11111111-1111-4111-8111-111111111111');
} catch (\Throwable $e) {
    [$status, $body] = ErrorResponder::render($e);
    echo "Infrastructure failure (database down):\n";
    echo "  -> HTTP {$status} " . json_encode($body) . "\n";
    echo "  The client gets a generic message + a reference; the SQL/trace is logged, not shown.\n\n";
}

echo "Same edge, two categories: 422 (business, explained) vs 500 (technical, hidden).\n";
