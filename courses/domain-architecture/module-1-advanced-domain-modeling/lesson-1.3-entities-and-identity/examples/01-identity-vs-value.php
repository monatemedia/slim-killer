<?php

declare(strict_types=1);

/**
 * Example 01 — Identity (entity) vs Value (value object)
 * -------------------------------------------------------------------------
 *   php examples/01-identity-vs-value.php
 */

require __DIR__ . '/../src/ApplicationId.php';
require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/BondApplication.php';

use Bond\Model\BondApplication;
use Bond\ValueObject\ApplicationId;

echo "=== Example 01 — Identity vs Value ===\n\n";

// VALUE OBJECT: two ApplicationIds built from the same string are equal by VALUE.
$idA = new ApplicationId('11111111-1111-4111-8111-111111111111');
$idB = new ApplicationId('11111111-1111-4111-8111-111111111111');
echo "ApplicationId (value object):\n";
echo "  same string -> equals():   " . ($idA->equals($idB) ? 'true' : 'false') . "\n";
echo "  same string -> ===:        " . ($idA === $idB ? 'true' : 'false (different objects — we do not care)') . "\n\n";

// ENTITY: two applications with IDENTICAL attributes are still different things.
$app1 = BondApplication::start();
$app2 = BondApplication::start();
echo "BondApplication (entity):\n";
echo "  app1 status:               {$app1->status()->value}\n";
echo "  app2 status:               {$app2->status()->value}  (identical attributes)\n";
echo "  app1->equals(app2):        " . ($app1->equals($app2) ? 'true' : 'false (different identities -> different applications)') . "\n";
echo "  app1 id:                   {$app1->id()->value}\n";
echo "  app2 id:                   {$app2->id()->value}\n\n";

echo "RULE: ask \"equal value?\" of a value object; ask \"same identity?\" of an entity.\n";
