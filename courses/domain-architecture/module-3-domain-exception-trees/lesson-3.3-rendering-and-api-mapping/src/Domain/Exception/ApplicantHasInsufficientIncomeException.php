<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

final class ApplicantHasInsufficientIncomeException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly int $monthlyIncomeCents,
        public readonly int $requiredInstalmentCents,
    ) {
        parent::__construct('Applicant income does not support the required instalment.');
    }

    #[\Override]
    public function context(): array
    {
        return [
            'application_id'            => $this->applicationId,
            'monthly_income_cents'      => $this->monthlyIncomeCents,
            'required_instalment_cents' => $this->requiredInstalmentCents,
        ];
    }
}
