<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;

/**
 * BondApplication — an ENTITY, defined by identity, not by its attributes.
 *
 * This is the mirror image of a Value Object (Lesson 1.2):
 *   - A Money is equal to another Money when their VALUES match. It has no identity.
 *   - A BondApplication is equal to another only when their IDENTITIES match. Two
 *     applications with byte-for-byte identical attributes are still DIFFERENT
 *     applications, because they are different real-world things.
 *
 * Crucially, identity is STABLE: the status changes over the application's life
 * (Draft -> Submitted -> Approved), but `id` never does — so `equals()` keeps
 * recognising it as the same application across every change.
 */
final class BondApplication
{
    public function __construct(
        private readonly ApplicationId $id,
        private ApplicationStatus $status = ApplicationStatus::Draft,
    ) {}

    /** Start a brand-new application with a freshly minted identity. */
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
        // Lifecycle guards are taught in Lesson 1.1; kept simple here to focus on identity.
        $this->status = ApplicationStatus::Approved;
    }

    /** Two entities are the same when their identities match — attributes are irrelevant. */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
