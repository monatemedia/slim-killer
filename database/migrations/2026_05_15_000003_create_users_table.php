<?php

declare(strict_types=1);

use Pixie\QueryBuilder\QueryBuilderHandler;

return new class {
    public function up(QueryBuilderHandler $db): void {
        $pdo = $db->getConnection()->getPdoInstance();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $primaryKey = ($driver === 'sqlite') 
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT' 
            : 'INT AUTO_INCREMENT PRIMARY KEY';

        $timestampDefault = ($driver === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';

        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` {$primaryKey},
            `username` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT {$timestampDefault},
            `updated_at` TIMESTAMP DEFAULT {$timestampDefault}
        );";

        $pdo->exec($sql);
    }

    public function down(QueryBuilderHandler $db): void {
        $pdo = $db->getConnection()->getPdoInstance();
        $pdo->exec("DROP TABLE IF EXISTS `users`;");
    }
};