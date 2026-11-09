<?php

declare(strict_types=1);

namespace SlimKiller\Database;

use PDO;
use Throwable;

/**
 * Brings the SQLite database up to date. Each file in database/migrations runs once,
 * in name order, inside a transaction, and is recorded in the migrations table.
 * Migrations only ever add: a released migration is never edited.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {}

    /**
     * Runs every migration that has not run yet.
     *
     * @return list<string> The names of the migrations that ran now.
     */
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, ran_at INTEGER NOT NULL)');

        $statement = $this->pdo->query('SELECT name FROM migrations');
        $done = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);
        $ran = [];

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $done, true)) {
                continue;
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec((string) file_get_contents($file));
                $this->pdo->prepare('INSERT INTO migrations (name, ran_at) VALUES (?, ?)')->execute([$name, time()]);
                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            $ran[] = $name;
        }

        return $ran;
    }

    /** @return array<string, string> Migration name => file path, in order. */
    private function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $file) {
            $files[basename($file, '.sql')] = $file;
        }
        ksort($files);

        return $files;
    }
}
