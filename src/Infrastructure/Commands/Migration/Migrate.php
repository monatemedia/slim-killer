<?php

namespace App\Infrastructure\Commands\Migration;

use App\Infrastructure\Commands\CommandInterface;
use Illuminate\Database\Capsule\Manager as Capsule;


class Migrate implements CommandInterface {

    public function getName(): string {
        return 'migrate';
    }

    public function getDescription(): string {
        return 'Runs the database migrations.';
    }

    public function handle(array $argv): void {
        ensureMigrationTableExists();
        $executed = Capsule::table('migrations')->pluck('migration')->toArray();
        $files = glob(__DIR__ . '/database/migrations/*.php');
        $batch = (int)(Capsule::table('migrations')->max('batch') ?? 0) + 1;
        $count = 0;

        if (empty(array_diff(array_map(fn($f) => basename($f, '.php'), $files), $executed))) {
            echo "Nothing to migrate.\n";
            exit(0);
        }

        // Start the transaction
        Capsule::connection()->beginTransaction();

        try {
            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (!in_array($name, $executed)) {
                    echo "Migrating: $name...\n";
                    
                    $migration = require $file;
                    $migration->up();
                    
                    Capsule::table('migrations')->insert([
                        'migration' => $name,
                        'batch' => $batch
                    ]);
                    $count++;
                }
            }
            
            // If we got here, everything worked!
            Capsule::connection()->commit();
            echo "\e[32mSuccessfully migrated $count files.\e[0m\n";

        } catch (\Exception $e) {
            // Something went wrong, undo the transaction
            Capsule::connection()->rollBack();
            
            echo "\n\e[31m[TRANSACTION ROLLED BACK]\e[0m\n";
            echo "\e[31m[ERROR]\e[0m " . $e->getMessage() . "\n";
            echo "No changes were saved to the migrations table.\n";
            exit(1);
        }
    }
}
