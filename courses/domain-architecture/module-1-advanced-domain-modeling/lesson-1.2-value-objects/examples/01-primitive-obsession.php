<?php

declare(strict_types=1);

/**
 * Example 01 — The Primitive Obsession Problem
 * -------------------------------------------------------------------------
 * This is the DISEASE. No value objects yet — just the primitives the current
 * Bond flow uses (`(float) $data['bond_amount']`, magic-string status, etc.).
 * Run it and watch how many ways a "valid" program produces nonsense.
 *
 *   php examples/01-primitive-obsession.php
 */

echo "=== Example 01 — Why primitives lie ===\n\n";

// 1. A bond amount is "just a float". Nothing stops it being negative.
$bondAmount = -1_250_000.00;
echo "1. Negative bond accepted silently:        R" . number_format($bondAmount, 2) . "\n";

// 2. Floats cannot represent money exactly.
$deposit = 0.1;
$fee     = 0.2;
echo "2. 0.1 + 0.2 === 0.3 ?                      " . (($deposit + $fee) === 0.3 ? 'true' : 'FALSE (!!)') . "\n";
echo "   Actual value of 0.1 + 0.2:              " . sprintf('%.17f', $deposit + $fee) . "\n";

// 3. Nothing ties an amount to a currency. You can add rands to dollars.
$amountInRands   = 1_000_000.00;
$amountInDollars = 50_000.00;
echo "3. Rands + dollars added blindly:          " . number_format($amountInRands + $amountInDollars, 2) . " (of what currency?)\n";

// 4. Status is a magic string. Every typo is a "valid" string.
$status = 'sumbitted'; // typo — still a perfectly valid string
echo "4. Misspelled status is still accepted:    '{$status}'\n";

// 5. An interest rate of 850% is a perfectly valid float.
$interestRate = 850.0;
echo "5. Absurd interest rate accepted:          {$interestRate}%\n";

echo "\nEvery line above is a latent bug the runtime happily allows.\n";
echo "Lesson 1.2 makes ALL of these impossible to even construct.\n";
