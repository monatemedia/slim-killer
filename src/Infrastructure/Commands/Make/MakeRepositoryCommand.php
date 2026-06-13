<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeRepositoryCommand implements CommandInterface {
    public function getName(): string { return 'make:repository'; }
    public function getDescription(): string { return 'Create a new database data mapping abstraction layer repository'; }

    public function handle(array $argv): void {
        if (!isset($argv[2])) { exit("\e[31m[ERROR] Missing repository name.\e[0m\n"); }
        $parsed = NameParser::parse($argv, 'Repository');
        
        // Target: src/Infrastructure/Persistence/
        $targetDir = dirname(__DIR__, 3) . "/src/Infrastructure/Persistence" . $parsed['relativeDir'];
        if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { exit("\e[31m[ERROR] Repository already exists.\e[0m\n"); }

        $template = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Infrastructure\Persistence{$parsed['subNamespace']};\n\nuse Pixie\QueryBuilder\QueryBuilderHandler;\n\nclass {$parsed['className']} {\n    /**\n     * Autowire the Pixie Query Builder wrapper layer into the data mapping persistence engine.\n     */\n    public function __construct(\n        private QueryBuilderHandler \$db\n    ) {}\n\n    /**\n     * Fetch record entities down from the database context.\n     */\n    public function all(): array {\n        // return \$this->db->table('change_me')->get();\n        return [];\n    }\n}\n";
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Infrastructure Persistence Repository:\e[0m {$filepath}\n";
    }
}