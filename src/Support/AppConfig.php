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
        /** The site's own name, as its owner knows it ("Acme Tours"). */
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
        /** Shared with the site's workflow, to sign "a build is ready". Empty: the webhook is off. */
        public string $webhookSecret = '',
        /** A test copy: web:install asks search engines not to list the website. */
        public bool $hideFromSearch = false,
        /** The business's time zone, like "Africa/Johannesburg": the visit counter's days follow it. */
        public string $timezone = 'UTC',
        /** The mailbox Slim Killer sends from, on the website's own domain. Empty: no email. */
        public string $mailFrom = '',
        /** Who gets the daily visit report. Empty: no report. */
        public string $visitReportTo = '',
        /** How many months single visit events are kept; null keeps them for good. 25 meets France's rules. */
        public ?int $visitEventsMonths = null,
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
            $text('WEBHOOK_SECRET'),
            in_array(strtolower($text('HIDE_FROM_SEARCH')), ['1', 'true', 'yes', 'on'], true),
            $text('TIMEZONE', 'UTC'),
            $text('MAIL_FROM'),
            $text('VISIT_REPORT_TO'),
            ctype_digit($text('VISIT_EVENTS_MONTHS')) && (int) $text('VISIT_EVENTS_MONTHS') > 0 ? (int) $text('VISIT_EVENTS_MONTHS') : null,
        );
    }

    /** A full address for a path on this website, or the path alone before install. */
    public function link(string $path): string
    {
        return $this->url . $path;
    }
}
