<?php

declare(strict_types=1);

namespace Bond\Domain\Exception;

use Bond\Domain\ValueObject\ApplicationId;
use Bond\Domain\ValueObject\Money;
use DomainException;

/**
 * A TYPED domain exception — the bridge to Module 3.
 *
 * Instead of returning false or throwing a generic error, the affordability rule throws
 * THIS: a named business event that carries safe, structured context (which application,
 * what income, what instalment). Application code can catch exactly this type, and in
 * Module 3 the HTTP layer maps it to a clean 422 response.
 *
 * For now it extends PHP's built-in \DomainException. In Module 3 it is reparented under
 * a proper tree: DomainException -> BondApplicationException -> this leaf.
 */
final class ApplicantHasInsufficientIncomeException extends DomainException
{
    public function __construct(
        public readonly ApplicationId $applicationId,
        public readonly Money $monthlyIncome,
        public readonly Money $requiredInstalment,
    ) {
        parent::__construct(sprintf(
            'Applicant income (%s/mo) does not support the required instalment (%s/mo).',
            $monthlyIncome->format(),
            $requiredInstalment->format(),
        ));
    }
}
