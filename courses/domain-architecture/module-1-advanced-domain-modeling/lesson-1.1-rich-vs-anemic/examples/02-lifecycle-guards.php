<?php

declare(strict_types=1);

/**
 * Example 02 — The lifecycle as a guarded state machine
 * -------------------------------------------------------------------------
 *   php examples/02-lifecycle-guards.php
 */

require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/BondApplication.php';

use Bond\Model\BondApplication;

echo "=== Example 02 — Lifecycle Guards ===\n\n";

// Happy path: draft -> submitted -> declined (with a reason).
$app = new BondApplication('lerato@example.co.za', 980_000_00);
echo "Start:               {$app->status()->value}\n";

$app->submit();
echo "After submit():      {$app->status()->value}\n";

$app->decline('Affordability check failed.');
echo "After decline():     {$app->status()->value}\n";
echo "Decline reason:      {$app->declineReason()}\n\n";

// Illegal moves are all rejected with a clear message.
$illegal = [
    'approve a fresh draft'        => fn () => (new BondApplication('a@b.co', 100_00))->approve(),
    'decline a fresh draft'        => fn () => (new BondApplication('a@b.co', 100_00))->decline('nope'),
    'submit twice'                 => function () {
        $a = new BondApplication('a@b.co', 100_00);
        $a->submit();
        $a->submit();
    },
    'decline with empty reason'    => function () {
        $a = new BondApplication('a@b.co', 100_00);
        $a->submit();
        $a->decline('   ');
    },
];

foreach ($illegal as $label => $move) {
    try {
        $move();
        echo "  {$label}: NOT rejected (!!)\n";
    } catch (DomainException | InvalidArgumentException $e) {
        echo "  Rejected — {$label}: {$e->getMessage()}\n";
    }
}
