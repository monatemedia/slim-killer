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

        $sql = "CREATE TABLE IF NOT EXISTS `applications` (
            `id` {$primaryKey},
            `first_name` VARCHAR(255) NOT NULL,
            `last_name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(100) NOT NULL,
            `province` VARCHAR(255) NULL,
            `city` VARCHAR(255) NULL,
            `bond_amount` DECIMAL(15, 2) NOT NULL,
            `message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT {$timestampDefault},
            `updated_at` TIMESTAMP DEFAULT {$timestampDefault}
        );";

        $pdo->exec($sql);
    }

    public function down(QueryBuilderHandler $db): void {
        $pdo = $db->getConnection()->getPdoInstance();
        $pdo->exec("DROP TABLE IF EXISTS `applications`;");
    }
};