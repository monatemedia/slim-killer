<?php

declare(strict_types=1);

namespace Bond\Domain\Rule;

use Bond\Domain\Exception\BondAmountExceedsPropertyValueException;

/**
 * BondRules — guards that throw leaves of the exception tree.
 *
 * Demonstrates the `never` return type: `rejectOverLeveraged()` ALWAYS throws, so it can
 * never return. Marking it `: never` documents that to humans and lets static analysers
 * know the code after a call to it is unreachable.
 */
final class BondRules
{
    public static function assertBondWithinPropertyValue(
        string $applicationId,
        int $bondAmountCents,
        int $propertyValueCents,
    ): void {
        if ($bondAmountCents > $propertyValueCents) {
            self::rejectOverLeveraged($applicationId, $bondAmountCents, $propertyValueCents);
        }
    }

    private static function rejectOverLeveraged(
        string $applicationId,
        int $bondAmountCents,
        int $propertyValueCents,
    ): never {
        throw new BondAmountExceedsPropertyValueException($applicationId, $bondAmountCents, $propertyValueCents);
    }
}
