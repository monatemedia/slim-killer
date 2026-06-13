<?php

namespace App\Infrastructure\Commands\Hammer;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeControllerCommand implements CommandInterface {
    public function getName(): string { return 'make:controller'; }
    public function getDescription(): string { return 'Create a new invokable single-action HTTP controller'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing controller name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Controller');
        $targetDir = dirname(__DIR__, 3) . "/app/Controllers" . $parsed['relativeDir'];
        
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Controller already exists.\e[0m\n"); }

        // Determine a friendly string title (e.g., "PropertySecrets" -> "Property Secrets")
        $rawTitle = str_replace('Controller', '', $parsed['className']);
        $friendlyTitle = preg_replace('/(?<!^)[A-Z]/', ' $0', $rawTitle);
        $viewName = strtolower(str_replace(' ', '', $friendlyTitle));

        // 🟢 ULTRACLEAN MASTER COURSE COMPLIANT TEMPLATE
        $template = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "namespace App\Controllers{$parsed['subNamespace']};\n\n"
            . "use Jenssegers\Blade\Blade;\n"
            . "use Psr\Http\Message\ResponseInterface as Response;\n"
            . "use Psr\Http\Message\ServerRequestInterface as Request;\n\n"
            . "class {$parsed['className']} {\n"
            . "    /**\n"
            . "     * Leverage constructor property promotion for a clean, autowired dependency layer.\n"
            . "     */\n"
            . "    public function __construct(\n"
            . "        private Blade \$blade\n"
            . "    ) {}\n\n"
            . "    /**\n"
            . "     * Handle the incoming HTTP request.\n"
            . "     */\n"
            . "    public function __invoke(Request \$request, Response \$response, array \$args): Response {\n"
            . "        // Render the view template matching the routing context\n"
            . "        \$html = \$this->blade->make('{$viewName}', ['title' => '{$friendlyTitle}'])->render();\n"
            . "        \n"
            . "        // Write the compiled HTML template to the PSR-7 stream response body\n"
            . "        \$response->getBody()->write(\|html);\n\n"
            . "        return \$response;\n"
            . "    }\n"
            . "}\n";

        // Fix potential string generation typo hook
        $template = str_replace('\|html', '$html', $template);

        file_put_contents($filepath, $template);
        echo "\e[32mCreated Invokable Controller class:\e[0m {$filepath}\n";
    }
}