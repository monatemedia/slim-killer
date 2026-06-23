<?php

declare(strict_types=1);

/**
 * Example 02 — Throw narrow, catch wide
 * -------------------------------------------------------------------------
 * The domain throws the most SPECIFIC leaf it can. The handler then chooses its ALTITUDE:
 * a specific leaf for bespoke handling, the bounded-context node for the rest, or the
 * shared root to catch a business failure from ANY context.
 *
 *   php examples/02-catch-at-the-right-altitude.php
 */

require __DIR__ . '/../autoload.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Exception\ApplicationAlreadySubmittedException;
use Bond\Domain\Exception\BondApplicationException;
use Bond\Shared\Exception\DomainException;

// A leaf from a DIFFERENT bounded context — also a DomainException, but NOT a bond one.
final class SubscriberAlreadyRegisteredException extends DomainException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct('This email is already subscribed.');
    }
}

$appId = '11111111-1111-4111-8111-111111111111';

function raise(string $scenario, string $appId): void
{
    throw match ($scenario) {
        'insufficient_income' => new ApplicantHasInsufficientIncomeException($appId, 3_000_000, 1_333_038),
        'already_submitted'   => new ApplicationAlreadySubmittedException($appId, 'submitted'),
        'other_context'       => new SubscriberAlreadyRegisteredException('thabo@example.co.za'),
    };
}

echo "=== Example 02 — Catch at the Right Altitude ===\n\n";

foreach (['insufficient_income', 'already_submitted', 'other_context'] as $scenario) {
    echo "Scenario '{$scenario}':\n";
    try {
        raise($scenario, $appId);
    } catch (ApplicationAlreadySubmittedException $e) {
        // SPECIFIC leaf, caught first for bespoke handling.
        echo "  [specific] redirect the user to their existing application ({$e->applicationId})\n\n";
    } catch (BondApplicationException $e) {
        // The whole bond context, one handler.
        echo "  [node]     HTTP 422 bond rejected: {$e->getMessage()}\n";
        echo "             context=" . json_encode($e->context()) . "\n\n";
    } catch (DomainException $e) {
        // Any business failure from any context.
        echo "  [root]     HTTP 422 domain failure (other context): {$e->getMessage()}\n\n";
    }
}

echo "Ordering matters: the most SPECIFIC catch comes first, the widest last.\n";
echo "Throw the narrowest exception; catch at whatever altitude the handler needs.\n";
