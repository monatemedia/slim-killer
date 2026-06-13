<?php

namespace App\Infrastructure\Commands\Cache;

use App\Infrastructure\Commands\CommandInterface;

class Clear implements CommandInterface {

    public function getName(): string {
        return 'cache:clear';
    }

    public function getDescription(): string {
        return 'Clears the Blade cache.';
    }

    public function handle(array $argv): void {
        echo "Clearing the clown's makeup (Blade cache)...\n";
        $files = glob(__DIR__ . '/storage/cache/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
        echo "\e[32mCache cleared!\e[0m\n";
    }
}
