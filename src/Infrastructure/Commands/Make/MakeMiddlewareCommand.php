<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeMiddlewareCommand implements CommandInterface {
    public function getName(): string { return 'make:middleware'; }
    public function getDescription(): string { return 'Create a new HTTP layer traffic middleware scaffold'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing middleware name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Middleware');
        
        // Target: src/Http/Middleware/
        $targetDir = dirname(__DIR__, 3) . "/src/Http/Middleware" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Middleware already exists.\e[0m\n"); }

        $template = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Http\Middleware{$parsed['subNamespace']};\n\nuse Psr\Http\Message\ServerRequestInterface as Request;\nuse Psr\Http\Server\RequestHandlerInterface as Handler;\nuse Psr\Http\Message\ResponseInterface as Response;\n\nclass {$parsed['className']} {\n    /**\n     * Process an incoming PSR-15 request traffic layer.\n     */\n    public function __invoke(Request \$request, Handler \$handler): Response {\n        // Before request interception logic goes here...\n        \n        \$response = \$handler->handle(\$request);\n        \n        // After request interception logic goes here...\n        return \$response;\n    }\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated PSR-15 Middleware:\e[0m {$filepath}\n";
    }
}