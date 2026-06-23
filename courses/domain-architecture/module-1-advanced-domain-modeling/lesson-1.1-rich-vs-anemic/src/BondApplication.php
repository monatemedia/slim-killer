<?php

declare(strict_types=1);

namespace Bond\Model;

use DomainException;
use InvalidArgumentException;

/**
 * BondApplication — a RICH model that protects its own lifecycle.
 *
 * Contrast with an anemic model (public `$status` + `setStatus()`), where any caller
 * can drop the object into any state at any time. Here the only way to change status is
 * to call an intent-revealing method (`submit()`, `approve()`, `decline()`), and each
 * method GUARDS the transition. Illegal states are unreachable, not "validated later".
 *
 * ENCAPSULATION ON PHP 8.3:
 *   `$status` is a private property exposed read-only via `status()`. On the course's
 *   target runtime (PHP 8.4+/8.5) you would instead write
 *       public private(set) ApplicationStatus $status;
 *   so it is readable as a property but writable only from inside the object — no getter
 *   needed. See ../php85-preview/bond-application-with-hooks.php.
 */
final class BondApplication
{
    private ApplicationStatus $status;
    private ?string $declineReason = null;

    public function __construct(
        private readonly string $applicantEmail,
        private readonly int $requestedAmountCents,
    ) {
        // An invariant enforced at birth — there is no valid zero/negative bond.
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

    /**
     * Tell, don't ask: the caller expresses intent ("submit this"), and the object
     * decides whether that is currently legal.
     */
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
