<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Make;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeRepositoryCommand implements CommandInterface {
    
    public function getName(): string { 
        return 'make:repository'; 
    }
    
    public function getDescription(): string { 
        return 'Create a new database data mapping abstraction layer repository'; 
    }

    public function handle(array $argv): void {
        // Explicit Argument Guard Blueprint
        if (!isset($argv[2]) || empty(trim($argv[2]))) {
            echo "\e[31m[ERROR] Command 'make:repository' requires exactly 1 argument, 0 provided.\e[0m\n\n";
            echo " \e[33mUsage Structure:\e[0m\n";
            echo "   php hammer make:repository [Domain Subfolder/]<Name>\n\n";
            echo " \e[33mExamples:\e[0m\n";
            echo "   php hammer make:repository Application/ApplicationRepository\n";
            echo "   php hammer make:repository Subscriber/Subscriber\n";
            exit(1);
        }

        $parsed = NameParser::parse($argv, 'Repository');
        
        // Target: src/Infrastructure/Persistence/[Domain]/ (4 levels out from inside Make folder)
        $targetDir = dirname(__DIR__, 4) . "/src/Infrastructure/Persistence" . $parsed['relativeDir'];
        
        if (!is_dir($targetDir)) { 
            mkdir($targetDir, 0755, true); 
        }
        
        $filepath = "{$targetDir}/{$parsed['className']}.php";
        if (file_exists($filepath)) { 
            exit("\e[31m[ERROR] Repository already exists at path: {$filepath}\e[0m\n"); 
        }

        $template = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Infrastructure\Persistence{$parsed['subNamespace']};\n\nuse Pixie\QueryBuilder\QueryBuilderHandler;\n\nclass {$parsed['className']} {\n\n    public function __construct(\n        private QueryBuilderHandler \$db\n    ) {}\n\n    /**\n     * Fetch record entities down from the database context.\n     */\n    public function all(): array {\n        // return \$this->db->table('change_me')->get();\n        return [];\n    }\n}\n";
        
        file_put_contents($filepath, $template);
        echo "\e[32mCreated Infrastructure Persistence Repository:\e[0m {$filepath}\n";
    }
}