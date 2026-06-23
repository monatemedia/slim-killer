<?php

declare(strict_types=1);

namespace Bond\Model;

use DomainException;
use InvalidArgumentException;

/**
 * 🏗️  CODE CHALLENGE — Make this model RICH
 * -------------------------------------------------------------------------
 * Refactor the anemic operations into rich, self-guarding methods. `submit()` is
 * done for you as a worked example. Implement `approve()` and `decline()` so that
 * each one ENFORCES its transition rather than blindly mutating state.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. A new application starts as Draft.
 *   2. submit():  Draft -> Submitted. (given)
 *   3. approve(): Submitted -> Approved. Throws \DomainException from any other status.
 *   4. decline(string $reason): Submitted -> Declined.
 *        - Throws \DomainException from any non-Submitted status.
 *        - Throws \InvalidArgumentException if the reason is blank.
 *        - Stores the reason (exposed via declineReason()).
 *
 * TIP: copy the guard pattern from `submit()`. Reuse the private guard helper.
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/BondApplication.php
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
        // TODO: enforce Submitted -> Approved (requirement 3)
        throw new \RuntimeException('TODO: implement BondApplication::approve()');
    }

    public function decline(string $reason): void
    {
        // TODO: enforce Submitted -> Declined, require a non-blank reason, store it (requirement 4)
        throw new \RuntimeException('TODO: implement BondApplication::decline()');
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
