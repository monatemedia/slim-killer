<?php

declare(strict_types=1);

namespace SlimKiller\Database;

use PDO;

/**
 * Opens the control panel's SQLite database: one file in the storage folder, outside
 * the web root.
 */
final class Database
{
    public const string FILE = 'database.sqlite';

    public static function open(string $path): PDO
    {
        if ($path !== ':memory:' && ! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    /** Has the given table been created yet (has `hammer install` or `migrate` run)? */
    public static function hasTable(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        $statement->execute([$table]);

        return $statement->fetchColumn() !== false;
    }
}
