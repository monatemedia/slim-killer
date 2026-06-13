<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Migration;

use App\Infrastructure\Commands\CommandInterface;
use Psr\Container\ContainerInterface;

class Migrate implements CommandInterface {

    public function __construct(
        private ContainerInterface $container
    ) {}

    public function getName(): string {
        return 'migrate';
    }

    public function getDescription(): string {
        return 'Runs outstanding system structural database migrations';
    }

    public function handle(array $argv): void {
        /** @var \Pixie\QueryBuilder\QueryBuilderHandler $db */
        $db = $this->container->get('db');
        $pdo = $db->getConnection()->getPdoInstance();

        // 1. Ensure the tracking migration blueprint state exists safely 
        $this->ensureMigrationTableExists($pdo);

        // 2. Resolve outstanding files
        $rootDir = dirname(__DIR__, 4);
        $files = glob($rootDir . '/database/migrations/*.php');
        sort($files);

        // Parse historically executed log references
        $executed = $db->table('migrations')->pluck('migration') ?: [];
        if (!is_array($executed)) {
            $executed = iterator_to_array($executed);
        }

        $batch = (int)($db->table('migrations')->max('batch') ?? 0) + 1;
        $count = 0;

        // Filter file pathways down to pending items
        $pending = array_filter($files, function($file) use ($executed) {
            return !in_array(basename($file, '.php'), $executed);
        });

        if (empty($pending)) {
            echo "Nothing to migrate.\n";
            return;
        }

        // 3. Process Execution Loop via secure single context transactions
        $pdo->beginTransaction();

        try {
            foreach ($pending as $file) {
                $name = basename($file, '.php');
                echo "Migrating: $name...\n";
                
                $migration = require $file;
                $migration->up($db);
                
                $db->table('migrations')->insert([
                    'migration' => $name,
                    'batch'     => $batch
                ]);
                $count++;
            }
            
            $pdo->commit();
            echo "\e[32mSuccessfully migrated $count files.\e[0m\n";

        } catch (\Exception $e) {
            $pdo->rollBack();
            echo "\n\e[31m[TRANSACTION ROLLED BACK]\e[0m\n";
            echo "\e[31m[ERROR]\e[0m " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function ensureMigrationTableExists(\PDO $pdo): void {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        $primaryKeyType = ($driver === 'sqlite') 
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT' 
            : 'INT AUTO_INCREMENT PRIMARY KEY';

        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` {$primaryKeyType},
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL
        );";

        $pdo->exec($sql);
    }
}