<?php

namespace App\Infrastructure\Commands\Hammer;

use App\Infrastructure\Commands\CommandInterface;
use App\Utils\NameParser;

class MakeCommandCommand implements CommandInterface {

    public function getName(): string {
        return 'make:command';
    }

    public function getDescription(): string {
        return 'Create a new custom Hammer command skeleton';
    }

    public function handle(array $argv): void {
        if (!isset($argv[2])) {
            echo "\e[31m[ERROR] Missing command configuration.\e[0m\n";
            echo "Usage: php hammer make:command [Category].[ClassName]\n";
            exit(1);
        }

        $parsed = NameParser::parse($argv, 'Command');
        $targetDir = dirname(__DIR__, 3) . "/app/Commands" . $parsed['relativeDir'];

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filepath = "{$targetDir}/{$parsed['className']}.php";

        if (file_exists($filepath)) {
            echo "\e[31m[ERROR] Command class already exists at:\e[0m {$filepath}\n";
            exit(1);
        }

        $signatureBase = strtolower(str_replace('Command', '', $parsed['className']));
        $commandSignature = $parsed['relativeDir'] ? strtolower(trim($parsed['relativeDir'], '/')) . ':' . $signatureBase : $signatureBase;

        $template = "<?php\n\nnamespace App\Infrastructure\Commands{$parsed['subNamespace']};\n\nuse App\Infrastructure\Commands\CommandInterface;\n\nclass {$parsed['className']} implements CommandInterface {\n\n    public function getName(): string {\n        return '{$commandSignature}';\n    }\n\n    public function getDescription(): string {\n        return 'Description for custom command {$commandSignature}';\n    }\n\n    public function handle(array \$argv): void {\n        echo \"Executing command {$commandSignature}...\\n\";\n    }\n}\n";

        file_put_contents($filepath, $template);
        echo "\e[32mCreated command class skeleton:\e[0m {$filepath}\n";

        echo "Optimizing framework autoloader mappings...\n";
        shell_exec('composer dump-autoload');
        echo "\e[32mAutoloader optimized successfully!\e[0m\n";
    }
}