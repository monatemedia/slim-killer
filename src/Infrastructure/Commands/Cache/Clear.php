<?php

declare(strict_types=1);

namespace App\Infrastructure\Commands\Cache;

use App\Infrastructure\Commands\CommandInterface;

class Clear implements CommandInterface {

    public function getName(): string {
        return 'cache:clear';
    }

    public function getDescription(): string {
        return 'Clears the application compiled Twig template cache view profiles';
    }

    public function handle(array $argv): void {
        echo "Clearing compiled view engine matrix...\n";
        
        $cacheDir = dirname(__DIR__, 4) . '/storage/cache/views';
        
        if (!is_dir($cacheDir)) {
            echo "\e[32mCache directory empty or unbuilt!\e[0m\n";
            return;
        }

        $di = new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);

        $count = 0;
        foreach ($ri as $file) {
            if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                unlink($file->getRealPath());
                $count++;
            }
        }
        
        echo "\e[32mCache completely cleared! Evicted {$count} compiled template files.\e[0m\n";
    }
}