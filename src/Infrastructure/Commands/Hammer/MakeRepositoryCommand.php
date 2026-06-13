<?php

namespace App\Infrastructure\Commands\Hammer;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeRepositoryCommand implements CommandInterface {
    public function getName(): string { return 'make:repository'; }
    public function getDescription(): string { return 'Create a new database data mapping abstraction layer repository'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing repository name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Repository');
        $targetDir = dirname(__DIR__, 3) . "/app/Repositories" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Repository already exists.\e[0m\n"); }

        $template = "<?php\n\nnamespace App\Repositories{$parsed['subNamespace']};\n\nclass {$parsed['className']} {\n    /**\n     * Fetch all associated raw model instances.\n     */\n    public function all() {\n        // return Model::all();\n    }\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Repository:\e[0m {$filepath}\n";
    }
}