<?php

declare(strict_types=1);

namespace Bond\Domain\Repository;

use Bond\Domain\Model\BondApplication;
use Bond\Domain\ValueObject\ApplicationId;

/**
 * BondApplicationRepository — a PURE domain interface.
 *
 * Look at what is, and is NOT, in this file:
 *   ✅ It speaks in AGGREGATES and IDENTITIES (BondApplication, ApplicationId).
 *   ❌ It mentions no SQL, no table names, no Pixie, no PDO, no arrays of columns.
 *
 * That is Rule D: a repository trades in aggregates, not rows. The domain owns this
 * contract; infrastructure must come to IT. To the domain it looks like an in-memory
 * collection of applications that just happens to survive between requests.
 *
 * The implementations live in src/Infrastructure/Persistence/ — and in the real Slim
 * Killer app, the production one is PixieBondApplicationRepository in
 * src/Infrastructure/Persistence/Bond/, wired to this interface in config/services.php.
 */
interface BondApplicationRepository
{
    /** Persist the whole aggregate as one unit (insert or update). */
    public function save(BondApplication $application): void;

    /** Reconstitute the aggregate with this identity, or null if none exists. */
    public function ofId(ApplicationId $id): ?BondApplication;
}
