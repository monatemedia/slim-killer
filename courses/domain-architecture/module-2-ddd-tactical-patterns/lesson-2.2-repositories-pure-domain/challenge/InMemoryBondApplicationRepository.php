<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\ApplicationId;

/**
 * 🏗️  CODE CHALLENGE — Implement the in-memory test double
 * -------------------------------------------------------------------------
 * Write the array-backed fake that satisfies BondApplicationRepository. This is the
 * implementation you would actually use to unit-test domain services — so it must obey
 * the SAME contract as the real database, or your tests will lie.
 *
 * REQUIREMENTS (the verifier runs the repository CONTRACT against your class):
 *   1. ofId() returns null for an id that was never saved.
 *   2. save() then ofId() returns an aggregate that is equal by identity.
 *   3. A round trip preserves the requested amount, status, and income sources.
 *   4. SNAPSHOT SEMANTICS: after save(), mutating the original aggregate must NOT change
 *      what ofId() later returns. (A real database stored a copy — so must you.)
 *      → clone on the way in, and clone on the way out.
 *
 * TIP: key the array on $application->id()->value. `clone` on a BondApplication makes a
 *      shallow copy; its income array is a value type and its items are immutable, so a
 *      shallow clone is a safe, independent snapshot.
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/InMemoryBondApplicationRepository.php
 */
final class InMemoryBondApplicationRepository implements BondApplicationRepository
{
    /** @var array<string, BondApplication> */
    private array $store = [];

    public function save(BondApplication $application): void
    {
        // TODO: store a snapshot keyed by the application's id (requirement 4)
        throw new \RuntimeException('TODO: implement save()');
    }

    public function ofId(ApplicationId $id): ?BondApplication
    {
        // TODO: return a snapshot of the stored aggregate, or null if absent (requirements 1 & 4)
        throw new \RuntimeException('TODO: implement ofId()');
    }
}
