<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

final class BondAmountExceedsPropertyValueException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly int $bondAmountCents,
        public readonly int $propertyValueCents,
    ) {
        parent::__construct('The requested bond exceeds the value of the property.');
    }

    #[\Override]
    public function context(): array
    {
        return [
            'application_id'       => $this->applicationId,
            'bond_amount_cents'    => $this->bondAmountCents,
            'property_value_cents' => $this->propertyValueCents,
        ];
    }
}
