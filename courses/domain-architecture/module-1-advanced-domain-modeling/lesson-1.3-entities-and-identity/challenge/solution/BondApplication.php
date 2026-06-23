<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;

/**
 * ✅ REFERENCE SOLUTION — identity-based equality
 *
 * `equals()` delegates entirely to the identity value object. Status is deliberately
 * ignored: an Approved application is still the same application it was as a Draft.
 */
final class BondApplication
{
    public function __construct(
        private readonly ApplicationId $id,
        private ApplicationStatus $status = ApplicationStatus::Draft,
    ) {}

    public static function start(): self
    {
        return new self(ApplicationId::generate());
    }

    public function id(): ApplicationId
    {
        return $this->id;
    }

    public function status(): ApplicationStatus
    {
        return $this->status;
    }

    public function approve(): void
    {
        $this->status = ApplicationStatus::Approved;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
