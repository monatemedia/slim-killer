<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;

/**
 * 🏗️  CODE CHALLENGE — Implement identity-based equality
 * -------------------------------------------------------------------------
 * This entity is complete except for `equals()`. Make it behave like a real entity:
 * two BondApplications are "the same" if and only if their IDENTITIES match — never
 * because their attributes happen to look alike.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. equals() compares by ApplicationId, NOT by status or any other attribute.
 *   2. Two separately started applications are never equal (distinct identities).
 *   3. An application equals a reconstructed copy carrying the SAME id (the
 *      "reloaded from storage" case), even though they are different PHP objects.
 *   4. Changing an attribute (approve()) must NOT change identity equality.
 *
 * TIP: ApplicationId already has its own value-based equals(). Lean on it.
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/BondApplication.php
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
        // TODO: return true only when the two applications share the same identity.
        throw new \RuntimeException('TODO: implement BondApplication::equals()');
    }
}
