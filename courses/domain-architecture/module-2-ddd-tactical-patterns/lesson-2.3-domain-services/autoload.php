<?php

declare(strict_types=1);

/** Tiny PSR-4 autoloader for this lesson: Bond\ -> src/ (mirrors composer's App\ -> src/). */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Bond\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
