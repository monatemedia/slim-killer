<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeServiceCommand implements CommandInterface {
    public function getName(): string { return 'make:service'; }
    public function getDescription(): string { return 'Create a new functional component driver backend service'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing service name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Service');
        
        // Target: src/Domain/Services/
        $targetDir = dirname(__DIR__, 3) . "/src/Domain/Services" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Service already exists.\e[0m\n"); }

        $template = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Domain\Services{$parsed['subNamespace']};\n\nclass {$parsed['className']} {\n    // Extensible domain core component service context...\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Domain Core Service class:\e[0m {$filepath}\n";
    }
}