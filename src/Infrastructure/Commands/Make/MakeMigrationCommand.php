<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;

class MakeMigrationCommand implements CommandInterface {
    
    public function getName(): string {
        return 'make:migration';
    }

    public function getDescription(): string {
        return 'Create a new database migration file stub';
    }

    public function handle(array $argv): void {
        if (!isset($argv[2])) {
            echo "\e[31m[ERROR] Missing migration name.\e[0m\n";
            echo "Usage:      php hammer make:migration [Name]\n";
            echo "Example:    php hammer make:migration CreateUsersTable\n";
            exit(1);
        }

        $name = $argv[2];
        $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $snakeName = str_replace('__', '_', $snakeName);

        $tableName = 'table_name';
        if (preg_match('/(?:create|update)_(.*)_table/', $snakeName, $matches)) {
            $tableName = $matches[1];
        }

        // FIXED PATH DEPTH: Climbs out of src/Infrastructure/Commands/Make to root (4 levels)
        $rootDir = dirname(__DIR__, 4);
        $dir = $rootDir . '/database/migrations';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$dir}/{$timestamp}_{$snakeName}.php";

        // Pure PHP cross-driver migration template
        $template = "<?php\n\ndeclare(strict_types=1);\n\nuse Pixie\QueryBuilder\QueryBuilderHandler;\n\nreturn new class {\n    public function up(QueryBuilderHandler \$db): void {\n        \$pdo = \$db->getConnection()->getPdoInstance();\n        \$driver = \$pdo->getAttribute(\\PDO::ATTR_DRIVER_NAME);\n\n        \$primaryKey = (\$driver === 'sqlite') \n            ? 'INTEGER PRIMARY KEY AUTOINCREMENT' \n            : 'INT AUTO_INCREMENT PRIMARY KEY';\n\n        \$timestampDefault = (\$driver === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';\n\n        \$sql = \"CREATE TABLE IF NOT EXISTS `{$tableName}` (\n            `id` {\$primaryKey},\n            `created_at` TIMESTAMP DEFAULT {\$timestampDefault},\n            `updated_at` TIMESTAMP DEFAULT {\$timestampDefault}\n        );\";\n\n        \$pdo->exec(\$sql);\n    }\n\n    public function down(QueryBuilderHandler \$db): void {\n        \$pdo = \$db->getConnection()->getPdoInstance();\n        \$pdo->exec(\"DROP TABLE IF EXISTS `{$tableName}`;\");\n    }\n};";

        file_put_contents($filename, $template);
        echo "\e[32mCreated migration stub:\e[0m $filename\n";
    }
}