<?php

declare(strict_types=1);

namespace Bond\Model;

use DomainException;
use InvalidArgumentException;

/**
 * ✅ REFERENCE SOLUTION — the rich BondApplication
 *
 * Every mutator follows the same shape: guard the current state, THEN transition.
 * The state machine lives inside the object, so no caller can reach an illegal state.
 */
final class BondApplication
{
    private ApplicationStatus $status;
    private ?string $declineReason = null;

    public function __construct(
        private readonly int $requestedAmountCents,
    ) {
        if ($requestedAmountCents <= 0) {
            throw new InvalidArgumentException('A bond application must request a positive amount.');
        }

        $this->status = ApplicationStatus::Draft;
    }

    public function status(): ApplicationStatus
    {
        return $this->status;
    }

    public function declineReason(): ?string
    {
        return $this->declineReason;
    }

    public function submit(): void
    {
        $this->guardCurrentStatusIs(ApplicationStatus::Draft, 'submitted');

        $this->status = ApplicationStatus::Submitted;
    }

    public function approve(): void
    {
        $this->guardCurrentStatusIs(ApplicationStatus::Submitted, 'approved');

        $this->status = ApplicationStatus::Approved;
    }

    public function decline(string $reason): void
    {
        $this->guardCurrentStatusIs(ApplicationStatus::Submitted, 'declined');

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A decline reason is required.');
        }

        $this->status = ApplicationStatus::Declined;
        $this->declineReason = $reason;
    }

    private function guardCurrentStatusIs(ApplicationStatus $required, string $action): void
    {
        if ($this->status !== $required) {
            throw new DomainException(
                "Only a {$required->value} application can be {$action}; current status is '{$this->status->value}'."
            );
        }
    }
}
