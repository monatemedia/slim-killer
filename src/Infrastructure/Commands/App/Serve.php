<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\App;

use App\Infrastructure\Commands\CommandInterface;

class Serve implements CommandInterface {

    public function getName(): string {
        return 'serve';
    }

    public function getDescription(): string {
        return 'Starts the Slim Killer development server';
    }

    public function handle(array $argv): void {
        $publicDir = dirname(__DIR__, 4) . '/public';
        
        echo "\e[32m[SLIM KILLER] Hammer is starting the engine on http://localhost:8000...\e[0m\n";
        passthru("php -S localhost:8000 -t " . escapeshellarg($publicDir));
    }
}