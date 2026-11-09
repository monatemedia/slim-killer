<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Settings from .env, read once at the entry point. `hammer install` writes the file.
 */
final readonly class AppConfig
{
    public const string DEFAULT_NAME = 'Slim Killer';

    public function __construct(
        /** The website's address, without a trailing slash; empty before install. */
        public string $url = '',
        /** The site's own name, as its owner knows it ("Honey Badger Namibia"). */
        public string $name = self::DEFAULT_NAME,
        /** Where the working copy of the client's content/ is; empty means the default in storage. */
        public string $contentDir = '',
        /** Where the collection schemas are; empty means the default in storage. */
        public string $schemaDir = '',
    ) {}

    /** @param array<mixed> $environment Usually $_ENV, after phpdotenv has loaded .env. */
    public static function fromEnvironment(array $environment): self
    {
        $url = $environment['APP_URL'] ?? '';
        $name = $environment['APP_NAME'] ?? '';
        $contentDir = $environment['CONTENT_DIR'] ?? '';
        $schemaDir = $environment['SCHEMA_DIR'] ?? '';

        return new self(
            is_string($url) ? rtrim($url, '/') : '',
            is_string($name) && trim($name) !== '' ? trim($name) : self::DEFAULT_NAME,
            is_string($contentDir) ? trim($contentDir) : '',
            is_string($schemaDir) ? trim($schemaDir) : '',
        );
    }

    /** A full address for a path on this website, or the path alone before install. */
    public function link(string $path): string
    {
        return $this->url . $path;
    }
}
