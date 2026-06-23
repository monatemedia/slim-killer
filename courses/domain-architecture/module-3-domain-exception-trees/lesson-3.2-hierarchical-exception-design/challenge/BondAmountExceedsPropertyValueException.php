<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

/**
 * 🏗️  CODE CHALLENGE — Add a leaf to the exception tree
 * -------------------------------------------------------------------------
 * Implement this leaf so it slots correctly into the hierarchy and exposes only safe
 * context. (The abstract root, the bounded-context node, and the other two leaves are
 * already provided in src/.)
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. It extends BondApplicationException (so it is also a DomainException + Throwable).
 *   2. Its message is exactly: "The requested bond exceeds the value of the property."
 *   3. context() returns EXACTLY these keys, with the constructor's values:
 *        application_id, bond_amount_cents, property_value_cents
 *   4. context() leaks nothing — only scalar values, no trace/sql/file keys.
 *      Use the #[\Override] attribute on context() (it implements the node's abstract method).
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/BondAmountExceedsPropertyValueException.php
 */
final class BondAmountExceedsPropertyValueException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly int $bondAmountCents,
        public readonly int $propertyValueCents,
    ) {
        // TODO: pass the correct safe business message to the parent (requirement 2)
        parent::__construct('TODO: set the business message');
    }

    #[\Override]
    public function context(): array
    {
        // TODO: return the safe structured context (requirement 3)
        return [];
    }
}
