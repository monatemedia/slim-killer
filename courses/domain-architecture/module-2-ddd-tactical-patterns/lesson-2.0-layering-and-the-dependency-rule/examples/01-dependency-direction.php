<?php

declare(strict_types=1);

/**
 * Example 01 — The Dependency Rule, demonstrated
 * -------------------------------------------------------------------------
 * The whole of this file is pure PHP — no Slim, no Pixie, no PDO. It proves the
 * core claim of Module 2: when dependencies point INWARD (infrastructure depends on
 * the domain, never the reverse), you can swap the entire persistence mechanism
 * WITHOUT touching a single line of domain code.
 *
 *   php examples/01-dependency-direction.php
 *
 * Read the three namespaces in order: Domain (knows nothing below it),
 * Infrastructure (depends on the Domain interface), then the Composition Root
 * (the ONLY place that names concrete classes — like public/index.php).
 */

namespace Bond\Domain {

    /**
     * A minimal domain entity. Notice: ZERO `use` statements pointing at a framework.
     * If you delete Slim/Pixie/Apache, everything in this namespace still compiles.
     */
    final class SubmittedApplication
    {
        public function __construct(public readonly string $reference) {}
    }

    /**
     * The domain OWNS this interface. It describes WHAT persistence must do in the
     * business's own words — "save an application", "how many do we have" — and says
     * nothing about HOW (no SQL, no table names, no driver).
     */
    interface ApplicationRepository
    {
        public function save(SubmittedApplication $application): void;

        public function count(): int;
    }

    /**
     * A domain service that depends ONLY on the interface above. It has no idea
     * whether it is talking to an array, MySQL, or a REST API.
     */
    final class SubmitApplication
    {
        public function __construct(
            private readonly ApplicationRepository $repository,
        ) {}

        public function handle(string $reference): void
        {
            $this->repository->save(new SubmittedApplication($reference));
        }
    }
}

namespace Bond\Infrastructure {

    use Bond\Domain\ApplicationRepository;
    use Bond\Domain\SubmittedApplication;

    /**
     * Infrastructure DEPENDS ON the domain interface (the arrow points inward).
     * Implementation #1: an in-memory array. Perfect for tests.
     */
    final class InMemoryApplicationRepository implements ApplicationRepository
    {
        /** @var array<string, SubmittedApplication> */
        private array $rows = [];

        public function save(SubmittedApplication $application): void
        {
            $this->rows[$application->reference] = $application;
        }

        public function count(): int
        {
            return count($this->rows);
        }
    }

    /**
     * Implementation #2: a stand-in for a SQL driver (Pixie/PDO would live here).
     * Same interface, completely different mechanism.
     */
    final class FakeSqlApplicationRepository implements ApplicationRepository
    {
        /** @var list<string> */
        private array $executedSql = [];

        public function save(SubmittedApplication $application): void
        {
            $this->executedSql[] = "INSERT INTO applications (reference) VALUES ('{$application->reference}')";
        }

        public function count(): int
        {
            return count($this->executedSql);
        }

        public function lastSql(): string
        {
            return $this->executedSql[array_key_last($this->executedSql)] ?? '';
        }
    }
}

namespace {

    // === COMPOSITION ROOT ==================================================
    // This is the ONLY place that mentions concrete classes — exactly like
    // Slim Killer's config/services.php and public/index.php. The domain above
    // never names an implementation; the root chooses one and injects it.

    use Bond\Domain\SubmitApplication;
    use Bond\Infrastructure\FakeSqlApplicationRepository;
    use Bond\Infrastructure\InMemoryApplicationRepository;

    echo "=== Example 01 — The Dependency Rule ===\n\n";

    // Wire the domain service to the IN-MEMORY implementation.
    $memoryRepo = new InMemoryApplicationRepository();
    $service    = new SubmitApplication($memoryRepo);
    $service->handle('APP-1001');
    $service->handle('APP-1002');
    echo "In-memory implementation:\n";
    echo "  applications stored:   {$memoryRepo->count()}\n\n";

    // Swap to the SQL implementation. The SubmitApplication domain code is UNCHANGED.
    $sqlRepo = new FakeSqlApplicationRepository();
    $service = new SubmitApplication($sqlRepo);
    $service->handle('APP-2002');
    echo "SQL implementation (same domain service, swapped at the root):\n";
    echo "  applications stored:   {$sqlRepo->count()}\n";
    echo "  last SQL executed:     {$sqlRepo->lastSql()}\n\n";

    echo "The domain service never changed. Only the composition root chose a different\n";
    echo "implementation. THAT is what 'dependencies point inward' buys you.\n";
}
