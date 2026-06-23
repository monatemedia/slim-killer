<?php

declare(strict_types=1);

/**
 * Example 02 — Three ways to report the SAME failure
 * -------------------------------------------------------------------------
 * An over-leveraged applicant is declined for affordability. Watch how much the
 * presentation layer can tell the applicant under each error strategy.
 *
 *   php examples/02-false-loses-the-reason.php
 */

echo "=== Example 02 — false vs generic vs typed ===\n\n";

// A TYPED domain exception carrying safe, structured context (what Module 2 introduced).
final class ApplicantHasInsufficientIncomeException extends DomainException
{
    public function __construct(
        public readonly int $monthlyIncomeCents,
        public readonly int $requiredInstalmentCents,
    ) {
        parent::__construct('Applicant income does not support the required instalment.');
    }
}

function rands(int $cents): string
{
    return 'R' . number_format($cents / 100, 2);
}

// The same business decision, reported three different ways. -----------------

// A) Return false — the style of the current SubmitApplicationAction::execute(): bool
function submit_returnFalse(): bool
{
    return false; // declined... but why? the reason is gone.
}

// B) Throw a generic exception — a type nobody can catch specifically, vague message.
function submit_throwGeneric(): void
{
    throw new RuntimeException('error');
}

// C) Throw a typed domain exception — catchable by type, safe message, rich context.
function submit_throwTyped(): void
{
    throw new ApplicantHasInsufficientIncomeException(3_000_000, 13_330_38);
}

// What can the PRESENTATION layer actually tell the applicant in each case? ---

echo "A) return false:\n";
$ok = submit_returnFalse();
echo "   The UI knows only: " . ($ok ? 'success' : '"Your application could not be processed."') . "\n";
echo "   It cannot say WHY, cannot guide the applicant, cannot branch on the reason.\n\n";

echo "B) throw generic RuntimeException:\n";
try {
    submit_throwGeneric();
} catch (\Throwable $e) {
    echo "   Caught \\Throwable — but is this a business decline or a real bug? Unknown.\n";
    echo "   The message ('{$e->getMessage()}') is useless to show, and unsafe in general.\n\n";
}

echo "C) throw ApplicantHasInsufficientIncomeException:\n";
try {
    submit_throwTyped();
} catch (ApplicantHasInsufficientIncomeException $e) {
    echo "   Caught the EXACT type — definitely a business decline, not a crash.\n";
    echo "   Safe message:   {$e->getMessage()}\n";
    echo "   With context:   income={" . rands($e->monthlyIncomeCents) . "}, instalment={" . rands($e->requiredInstalmentCents) . "}\n";
    echo "   The UI can say: \"Based on a monthly income of " . rands($e->monthlyIncomeCents)
        . ", the instalment of " . rands($e->requiredInstalmentCents) . " is too high.\"\n\n";
}

echo "Only the typed exception preserves the REASON and the DATA. `false` threw the reason\n";
echo "away; the generic exception kept it unstructured and uncatchable. Module 3 builds the\n";
echo "tree of typed exceptions and maps each to a precise, safe HTTP response.\n";
