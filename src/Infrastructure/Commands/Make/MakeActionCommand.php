<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeActionCommand implements CommandInterface {
    public function getName(): string { return 'make:action'; }
    public function getDescription(): string { return 'Create a new single-responsibility business action class'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing action name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Action');
        
        // Target: src/Domain/{Context}/{Action}
        $targetDir = dirname(__DIR__, 3) . "/src/Domain" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Action already exists.\e[0m\n"); }

        $template = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Domain{$parsed['subNamespace']};\n\nclass {$parsed['className']} {\n    /**\n     * Execute the core domain business action.\n     */\n    public function execute(... \$args) {\n        // Logic goes here\n    }\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Domain Action class:\e[0m {$filepath}\n";
    }
}