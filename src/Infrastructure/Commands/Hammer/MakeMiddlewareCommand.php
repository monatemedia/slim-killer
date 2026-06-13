<?php

namespace App\Infrastructure\Commands\Hammer;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeMiddlewareCommand implements CommandInterface {
    public function getName(): string { return 'make:middleware'; }
    public function getDescription(): string { return 'Create a new HTTP layer traffic middleware scaffold'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing middleware name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Middleware');
        $targetDir = dirname(__DIR__, 3) . "/app/Middleware" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Middleware already exists.\e[0m\n"); }

        $template = "<?php\n\nnamespace App\Middleware{$parsed['subNamespace']};\n\nclass {$parsed['className']} {\n    /**\n     * Process an incoming request traffic layer.\n     */\n    public function __invoke(\$request, \$handler) {\n        // Before logic goes here\n        \$response = \$handler->handle(\$request);\n        // After logic goes here\n        return \$response;\n    }\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Middleware:\e[0m {$filepath}\n";
    }
}