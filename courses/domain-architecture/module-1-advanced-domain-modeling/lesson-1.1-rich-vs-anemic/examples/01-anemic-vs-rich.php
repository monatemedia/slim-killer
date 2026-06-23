<?php

declare(strict_types=1);

/**
 * Example 01 — Anemic vs Rich, side by side
 * -------------------------------------------------------------------------
 *   php examples/01-anemic-vs-rich.php
 */

require __DIR__ . '/../src/ApplicationStatus.php';
require __DIR__ . '/../src/BondApplication.php';

use Bond\Model\BondApplication;

/**
 * The ANEMIC model: a public string the world can scribble on. It has getters/setters
 * but no rules — the rules, if they exist at all, live in some other class.
 */
final class AnemicApplication
{
    public string $status = 'draft';

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status; // no guard — anything goes
    }
}

echo "=== Example 01 — Anemic vs Rich ===\n\n";

// --- ANEMIC: every invalid move succeeds ---
echo "ANEMIC model:\n";
$anemic = new AnemicApplication();
$anemic->setStatus('approved');        // approved straight from draft — skipped submission!
echo "  Draft jumped to:        '{$anemic->getStatus()}' (never submitted — allowed)\n";
$anemic->setStatus('aproved');         // typo — still a "valid" string
echo "  Typo status accepted:   '{$anemic->getStatus()}'\n\n";

// --- RICH: the object defends its own lifecycle ---
echo "RICH model:\n";
$rich = new BondApplication('thabo@example.co.za', 1_250_000_00);
echo "  Born as:                '{$rich->status()->value}'\n";

try {
    $rich->approve();                  // illegal: cannot approve a draft
} catch (DomainException $e) {
    echo "  approve() on draft:     REJECTED — {$e->getMessage()}\n";
}

$rich->submit();
$rich->approve();
echo "  Legal submit->approve:  '{$rich->status()->value}'\n\n";

echo "The anemic object cannot stop nonsense. The rich object cannot be put into nonsense.\n";
