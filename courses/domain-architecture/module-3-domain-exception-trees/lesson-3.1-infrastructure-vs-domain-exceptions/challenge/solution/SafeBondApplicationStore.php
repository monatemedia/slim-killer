<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Infrastructure\Exception\PersistenceException;
use PDO;
use PDOException;

/** ✅ REFERENCE SOLUTION — the wrapped persistence boundary. */
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
            throw new PersistenceException('Could not persist the bond application.', previous: $e);
        }
    }
}
