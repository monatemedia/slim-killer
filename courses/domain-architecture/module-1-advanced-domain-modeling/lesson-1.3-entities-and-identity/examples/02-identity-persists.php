<?php

declare(strict_types=1);

/**
 * Example 02 — Identity is stable across attribute changes and reloads
 * -------------------------------------------------------------------------
 *   php examples/02-identity-persists.php
 */

require __DIR__ . '/../src/ApplicationId.php';
require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/BondApplication.php';

use Bond\Model\BondApplication;

echo "=== Example 02 — Identity Persists ===\n\n";

$app = BondApplication::start();
$originalId = $app->id()->value;

echo "Start:               status={$app->status()->value}  id={$app->id()->value}\n";

// Mutate an attribute — the status changes, the identity does NOT.
$app->approve();
echo "After approve():     status={$app->status()->value}  id={$app->id()->value}\n";
echo "Identity unchanged:  " . ($app->id()->value === $originalId ? 'true' : 'false') . "\n\n";

// Simulate loading the "same" application back from storage: a NEW object, SAME id.
// This is the preview of repositories (Module 2): persistence reconstitutes identity.
$reloaded = new BondApplication($app->id());
echo "Reloaded copy (new object, same id):\n";
echo "  app->equals(reloaded):   " . ($app->equals($reloaded) ? 'true (recognised as the same application)' : 'false') . "\n";
echo "  app === reloaded:        " . ($app === $reloaded ? 'true' : 'false (different PHP objects — identity equality still holds)') . "\n";
