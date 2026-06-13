<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Debug;

use App\Infrastructure\Commands\CommandInterface;
use Psr\Container\ContainerInterface;

class ContainerList implements CommandInterface {

    public function __construct(
        private ContainerInterface $container
    ) {}

    public function getName(): string {
        return 'debug:container';
    }

    public function getDescription(): string {
        return 'Inspect and profile service definitions bound inside the PHP-DI container';
    }

    public function handle(array $argv): void {
        echo "\e[1;33m [DI CONTAINER SERVICES MATRIX]\e[0m\n\n";

        // Locate the absolute definition file pathway
        $servicesPath = dirname(__DIR__, 4) . '/config/services.php';

        if (!file_exists($servicesPath)) {
            echo "\e[31m[ERROR] Could not read services config array at: {$servicesPath}\e[0m\n";
            exit(1);
        }

        // Read the raw definitions array directly to see what was explicitly configured
        $definitions = require $servicesPath;

        if (!is_array($definitions)) {
            echo "\e[31m[ERROR] config/services.php must return a valid PHP array matrix.\e[0m\n";
            exit(1);
        }

        printf(" \e[33m%-45s %-20s %-8s\e[0m\n", "Registered Service Key / Profile", "Definition Type", "Status");
        echo " " . str_repeat("-", 76) . "\n";

        foreach ($definitions as $key => $definition) {
            // 1. Evaluate the definition type
            $type = 'Unknown';
            if ($definition instanceof \Closure) {
                $type = 'Factory Closure';
            } elseif (is_object($definition)) {
                $type = get_class($definition);
            } elseif (is_string($definition)) {
                $type = 'Class / String Map';
            } elseif (is_array($definition)) {
                $type = 'Array Config';
            }

            // 2. Perform a live diagnostic resolution check
            $status = "\e[32m[ OK ]\e[0m";
            try {
                $this->container->get($key);
            } catch (\Exception $e) {
                $status = "\e[31m[FAIL]\e[0m";
            }

            // Shorten exceptionally long namespaces visually for table alignment
            $displayKey = strlen($key) > 43 ? '...' . substr($key, -40) : $key;

            printf("  %-44s %-19s %-8s\n", $displayKey, $type, $status);
        }
        echo "\n";
    }
}