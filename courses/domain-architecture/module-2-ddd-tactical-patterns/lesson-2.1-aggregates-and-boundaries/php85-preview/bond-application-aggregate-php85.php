<?php

declare(strict_types=1);

/**
 * ⚠️  PHP 8.4 / 8.5 PREVIEW — DO NOT RUN ON PHP 8.3 OR BELOW
 * -------------------------------------------------------------------------
 * Uses syntax that PARSE-ERRORS on older runtimes:
 *   - asymmetric visibility  `public private(set)`   (PHP 8.4)
 *   - `clone with [...]`                              (PHP 8.5)
 *
 * Two upgrades to the aggregate on the target runtime:
 *   1. `public private(set)` exposes status/requestedAmount as read-only PROPERTIES —
 *      no getter methods needed, and still unwritable from outside.
 *   2. Lifecycle transitions can be modelled IMMUTABLY with `clone with`, returning a
 *      new aggregate state instead of mutating in place. (Cloning copies the income
 *      array, which is a PHP value type, so the new state is fully independent.)
 *
 *   herd use 8.5 && php php85-preview/bond-application-aggregate-php85.php
 */

namespace Bond\Preview;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}

final class BondApplication
{
    /** @var list<string> simplified line items for the preview */
    private array $incomeSources = [];

    // PHP 8.4: read it like a property anywhere; only this class can write it.
    public private(set) ApplicationStatus $status = ApplicationStatus::Draft;

    public function withIncomeSource(string $source): static
    {
        if ($this->status !== ApplicationStatus::Draft) {
            throw new \DomainException('Income is locked after submission.');
        }

        // PHP 8.5: produce a new aggregate state with one field changed.
        $next = clone $this with [];
        $next->incomeSources[] = $source; // the clone has its own copy of the array
        return $next;
    }

    public function submit(): static
    {
        if ($this->incomeSources === []) {
            throw new \DomainException('Cannot submit with no income.');
        }

        return clone $this with ['status' => ApplicationStatus::Submitted];
    }

    public function incomeCount(): int
    {
        return count($this->incomeSources);
    }
}

$draft     = new BondApplication();
$withIncome = $draft->withIncomeSource('Acme Mining');
$submitted = $withIncome->submit();

echo "Original draft income count: {$draft->incomeCount()} (unchanged)\n";   // 0
echo "With income count:           {$withIncome->incomeCount()}\n";          // 1
echo "Submitted status:            {$submitted->status->value}\n";           // submitted (read as property)
