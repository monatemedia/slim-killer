<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\ApplicationId;

/**
 * InMemoryBondApplicationRepository — a fast, dependency-free implementation backed by
 * an array. This is the test double you reach for when unit-testing domain services
 * (prequel Module 5: "Test Behaviours, Not Layouts"). It honours the SAME interface as
 * the database-backed repository, so the code under test cannot tell them apart.
 *
 * SNAPSHOT SEMANTICS: a real database stores a copy of your data at save() time, so
 * mutating the aggregate afterwards does not change what is stored. We replicate that by
 * cloning on save() and on ofId() — otherwise this fake would be lying about how
 * persistence behaves, and tests that pass here would fail against the real database.
 */
final class InMemoryBondApplicationRepository implements BondApplicationRepository
{
    /** @var array<string, BondApplication> keyed by ApplicationId value */
    private array $store = [];

    public function save(BondApplication $application): void
    {
        // clone = take a snapshot; the caller's later mutations must not leak in here.
        $this->store[$application->id()->value] = clone $application;
    }

    public function ofId(ApplicationId $id): ?BondApplication
    {
        $found = $this->store[$id->value] ?? null;

        // clone again so the caller cannot reach back into our stored snapshot.
        return $found === null ? null : clone $found;
    }
}
