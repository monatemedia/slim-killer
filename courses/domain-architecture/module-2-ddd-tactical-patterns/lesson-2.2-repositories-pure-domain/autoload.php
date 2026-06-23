<?php

declare(strict_types=1);

/**
 * Tiny PSR-4 autoloader for this lesson.
 *
 * Maps the `Bond\` namespace to this lesson's `src/` directory, mirroring how Slim
 * Killer's composer.json maps `App\` -> `src/`. Examples and the verifier require this
 * file instead of listing every class by hand — which also lets us SEE the layer
 * boundary: domain classes resolve under src/Domain/, implementations under
 * src/Infrastructure/, and nothing in the domain ever needs the infrastructure files.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Bond\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
