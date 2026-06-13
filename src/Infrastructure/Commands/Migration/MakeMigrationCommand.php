<?php

namespace App\Infrastructure\Commands\Migration;

use App\Infrastructure\Commands\CommandInterface;

class MakeMigrationCommand implements CommandInterface {
    
    public function getName(): string {
        return 'make:migration';
    }

    public function getDescription(): string {
        return 'Create a new database migration file stub';
    }

    public function handle(array $argv): void {
        // Corrected Validation: Looks at $argv[2] for the migration name
        if (!isset($argv[2])) {
            echo "\e[31m[ERROR] Missing migration name.\e[0m\n";
            echo "Usage:   php hammer make:migration [Name]\n";
            echo "Example: php hammer make:migration CreateBlogPostsTable\n";
            exit(1);
        }

        $name = $argv[2];

        // 1. Transform PascalCase / camelCase / snake_case cleanly
        $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $snakeName = str_replace('__', '_', $snakeName);

        // 2. Dynamic Table Name Guessing
        $tableName = 'table_name';
        if (preg_match('/(?:create|update)_(.*)_table/', $snakeName, $matches)) {
            $tableName = $matches[1];
        }

        // 3. Absolute Path Generation & Directory Autocreation
        // Climes out of 'app/Commands/Migration' (3 levels) to root folder
        $rootDir = dirname(__DIR__, 3);
        $dir = $rootDir . '/database/migrations';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$dir}/{$timestamp}_{$snakeName}.php";

        // 4. Blueprint Generation
        $template = "<?php\n\nuse Illuminate\Database\Schema\Blueprint;\nuse Illuminate\Database\Capsule\Manager as Capsule;\n\nreturn new class {\n    public function up() {\n        Capsule::schema()->create('$tableName', function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });\n    }\n\n    public function down() {\n        Capsule::schema()->dropIfExists('$tableName');\n    }\n};";

        file_put_contents($filename, $template);
        echo "\e[32mCreated migration:\e[0m $filename\n";
    }
}