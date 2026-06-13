<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeControllerCommand implements CommandInterface {
    public function getName(): string { return 'make:controller'; }
    public function getDescription(): string { return 'Create a new invokable single-action HTTP controller'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing controller name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Controller');
        
        // Target: src/Http/{Context}/{Controller}
        $targetDir = dirname(__DIR__, 3) . "/src/Http" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Controller already exists.\e[0m\n"); }

        $rawTitle = str_replace('Controller', '', $parsed['className']);
        $friendlyTitle = preg_replace('/(?<!^)[A-Z]/', ' $0', $rawTitle);
        $viewName = strtolower(str_replace(' ', '', $friendlyTitle));

        $template = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "namespace App\Http{$parsed['subNamespace']};\n\n"
            . "use Slim\Views\Twig;\n"
            . "use Psr\Http\Message\ResponseInterface as Response;\n"
            . "use Psr\Http\Message\ServerRequestInterface as Request;\n\n"
            . "class {$parsed['className']} {\n"
            . "    /**\n"
            . "     * Leverage constructor promotion to autowire Slim Killer's standard Twig view engine.\n"
            . "     */\n"
            . "    public function __construct(\n"
            . "        private Twig \$view\n"
            . "    ) {}\n\n"
            . "    /**\n"
            . "     * Handle the incoming HTTP request.\n"
            . "     */\n"
            . "    public function __invoke(Request \$request, Response \$response, array \$args): Response {\n"
            . "        return \$this->view->render(\$response, '{$viewName}.twig', [\n"
            . "            'title' => '{$friendlyTitle}'\n"
            . "        ]);\n"
            . "    }\n"
            . "}\n";

        file_put_contents($filepath, $template);
        echo "\e[32mCreated Invokable HTTP Controller class:\e[0m {$filepath}\n";
    }
}