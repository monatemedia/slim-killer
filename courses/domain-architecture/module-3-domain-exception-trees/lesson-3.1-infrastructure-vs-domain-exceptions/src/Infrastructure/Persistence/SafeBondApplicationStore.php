<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Infrastructure\Exception\PersistenceException;
use PDO;
use PDOException;

/**
 * SafeBondApplicationStore — demonstrates WRAPPING at the persistence boundary.
 *
 * This is the only place that touches PDO, and it is also the place responsible for
 * making sure PDO's exceptions never escape into the rest of the application. Every PDO
 * call is wrapped: a raw PDOException is caught and re-thrown as a PersistenceException
 * (with the original preserved as $previous). Callers — domain services, controllers —
 * therefore never need to know that PDO, or even "SQL", exists.
 */
final class SafeBondApplicationStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function save(string $applicationId): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO applications (id) VALUES (:id)');
            $stmt->execute([':id' => $applicationId]);
        } catch (PDOException $e) {
            // Translate the driver failure into a clean infrastructure concept.
            throw new PersistenceException('Could not persist the bond application.', previous: $e);
        }
    }
}
