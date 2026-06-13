<?php

namespace App\Infrastructure\Commands\App;

use App\Infrastructure\Commands\CommandInterface;

class Serve implements CommandInterface {

    public function getName(): string {
        return 'serve';
    }

    public function getDescription(): string {
        return 'Starts the application dev server.';
    }

    public function handle(array $argv): void {
        echo "\e[32mHammer is starting the show on http://localhost:8000...\e[0m\n";
        passthru('php -S localhost:8000 -t public');
    }
}
