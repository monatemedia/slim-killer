<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

/**
 * A DOMAIN failure: the applicant's income does not support the instalment.
 * Meaningful, intentional, and safe to explain to the applicant. It carries structured
 * context (income, instalment) for a precise message — and it is a DomainException, so
 * the edge routes it to a 422, not a 500.
 */
final class ApplicantHasInsufficientIncomeException extends DomainException
{
    public function __construct(
        public readonly int $monthlyIncomeCents,
        public readonly int $requiredInstalmentCents,
    ) {
        parent::__construct('Applicant income does not support the required instalment.');
    }
}
