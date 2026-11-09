<?php

declare(strict_types=1);

namespace SlimKiller\Content;

use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Paths;

/**
 * Where the control panel's working copy is: the client's content/ folder, and the
 * collection schemas that Astro generates. On a client's host both live in the storage
 * folder and are refreshed by every save and fetch; for local development, .env can
 * point them at an Astro site on the same machine.
 */
final readonly class ContentLocation
{
    public function __construct(
        public string $contentDir,
        public string $schemaDir,
    ) {}

    public static function from(AppConfig $config, Paths $paths): self
    {
        $resolve = static function (string $configured, string $default) use ($paths): string {
            if ($configured === '') {
                return $default;
            }
            $absolute = preg_match('#^([A-Za-z]:)?[/\\\\]#', $configured) === 1;

            return rtrim($absolute ? $configured : $paths->root($configured), '/\\');
        };

        return new self(
            $resolve($config->contentDir, $paths->storage('site/content')),
            $resolve($config->schemaDir, $paths->storage('site/schemas')),
        );
    }
}
