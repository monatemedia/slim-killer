<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Infrastructure\Exception\PersistenceException;
use PDO;
use PDOException;

/**
 * 🏗️  CODE CHALLENGE — Wrap the persistence boundary
 * -------------------------------------------------------------------------
 * Implement save() so that a raw PDO failure is NEVER allowed to escape this class. The
 * boundary's contract: callers see PersistenceException (an infrastructure concept),
 * never PDOException (a driver detail).
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. On success (the table exists), save() inserts the row and returns without throwing.
 *   2. On a PDO failure (e.g. the table is missing), save() throws a PersistenceException.
 *   3. A raw PDOException must NOT escape save() — it is caught and translated.
 *   4. The original PDOException is preserved as the PersistenceException's $previous
 *      (so the full technical detail is available for server-side logging).
 *
 * TIP: prepare + execute 'INSERT INTO applications (id) VALUES (:id)', wrapped in
 *      try/catch (PDOException $e) { throw new PersistenceException('...', previous: $e); }
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/SafeBondApplicationStore.php
 */
final class SafeBondApplicationStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function save(string $applicationId): void
    {
        // TODO: run the INSERT and translate any PDOException into a PersistenceException.
        throw new \RuntimeException('TODO: implement save()');
    }
}
