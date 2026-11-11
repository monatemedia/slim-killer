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
        /** The client site's repository on GitHub, "owner/name"; empty until saving is set up. */
        public string $githubRepository = '',
        /** The branch saves go to. */
        public string $githubBranch = 'main',
        /** A fine-grained token with contents write on that one repository. */
        public string $githubToken = '',
        /** The folder in the repository that holds content/. */
        public string $githubContentFolder = 'content',
        /** The folder the website is served from; releases go in its _site/. Empty until set. */
        public string $webRoot = '',
    ) {}

    /** @param array<mixed> $environment Usually $_ENV, after phpdotenv has loaded .env. */
    public static function fromEnvironment(array $environment): self
    {
        $url = $environment['APP_URL'] ?? '';
        $name = $environment['APP_NAME'] ?? '';
        $contentDir = $environment['CONTENT_DIR'] ?? '';
        $schemaDir = $environment['SCHEMA_DIR'] ?? '';
        $text = static fn(string $key, string $default = ''): string => is_string($environment[$key] ?? null) && trim($environment[$key]) !== ''
            ? trim($environment[$key])
            : $default;

        return new self(
            is_string($url) ? rtrim($url, '/') : '',
            is_string($name) && trim($name) !== '' ? trim($name) : self::DEFAULT_NAME,
            is_string($contentDir) ? trim($contentDir) : '',
            is_string($schemaDir) ? trim($schemaDir) : '',
            $text('GITHUB_REPOSITORY'),
            $text('GITHUB_BRANCH', 'main'),
            $text('GITHUB_TOKEN'),
            $text('GITHUB_CONTENT_FOLDER', 'content'),
            $text('WEB_ROOT'),
        );
    }

    /** A full address for a path on this website, or the path alone before install. */
    public function link(string $path): string
    {
        return $this->url . $path;
    }
}
