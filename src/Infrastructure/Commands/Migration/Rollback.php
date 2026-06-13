<?php

namespace App\Infrastructure\Commands\Migration;

use App\Infrastructure\Commands\CommandInterface;

class Rollback implements CommandInterface {

    public function getName(): string {
        return 'migration:rollback';
    }

    public function getDescription(): string {
        return 'Description for custom command migration:rollback';
    }

    public function handle(array $argv): void {
        echo "Executing command migration:rollback...\n";
    }
}
