<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Domain\Model\BondApplication;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\ApplicationId;

/**
 * ✅ REFERENCE SOLUTION — array-backed fake with snapshot semantics.
 */
final class InMemoryBondApplicationRepository implements BondApplicationRepository
{
    /** @var array<string, BondApplication> */
    private array $store = [];

    public function save(BondApplication $application): void
    {
        $this->store[$application->id()->value] = clone $application;
    }

    public function ofId(ApplicationId $id): ?BondApplication
    {
        $found = $this->store[$id->value] ?? null;

        return $found === null ? null : clone $found;
    }
}
