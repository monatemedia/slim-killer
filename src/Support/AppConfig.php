<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Settings from .env, read once at the entry point. `hammer install` writes the file.
 */
final readonly class AppConfig
{
    public const string DEFAULT_NAME = 'Slim Killer';

    /** The folder in the root of the site's Google Drive that backups go to. */
    public const string DEFAULT_BACKUP_FOLDER = 'Website backups';

    /** How many backups Google Drive keeps: two years of one a day. */
    public const int DEFAULT_BACKUPS_KEPT = 731;

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
        /** A "Desktop app" OAuth client in the site's own Google account. Empty: backups stay on this server. */
        public string $googleClientId = '',
        public string $googleClientSecret = '',
        /** The folder in the root of that Google Drive the backups go to. */
        public string $backupFolder = self::DEFAULT_BACKUP_FOLDER,
        /** How many backups that folder keeps; the oldest go first. */
        public int $backupsKept = self::DEFAULT_BACKUPS_KEPT,
        /** 64 hexadecimal characters that encrypt the copies sent to Google Drive. Kept in a password manager too. */
        public string $backupKey = '',
        /** BACKUPS="off": no daily backup. Losing the database then loses accounts and visit history, never pages. */
        public bool $backupsOff = false,
        /** Who gets an email when something stops working; empty: VISIT_REPORT_TO. */
        public string $alertsTo = '',
        /** The website's own mail server, like "smtp.titan.email". Empty: PHP's mail(). */
        public string $smtpHost = '',
        /** 465 (encrypted at once) or 587 (STARTTLS). */
        public int $smtpPort = 465,
        /** The mailbox to sign in as; empty means MAIL_FROM. */
        public string $smtpUser = '',
        /** That mailbox's password. A secret: only in .env. */
        public string $smtpPassword = '',
        /** Where this runs: local, staging or production (APP_ENV). */
        public AppEnv $env = AppEnv::Production,
        /** APP_ENV as written, for the doctor to name one it doesn't know. */
        public string $envSetting = '',
        /** Was the mode read from the older LOCAL_WEBSITE or HIDE_FROM_SEARCH? */
        public bool $envFromOldSettings = false,
    ) {}

    public function isLocal(): bool
    {
        return $this->env === AppEnv::Local;
    }

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

        $yes = static fn(string $key): bool => in_array(strtolower($text($key)), ['1', 'true', 'yes', 'on'], true);
        $env = AppEnv::fromSetting($text('APP_ENV'));
        $fromOld = false;
        if ($env === null && $text('APP_ENV') === '') {
            $fromOld = $yes('LOCAL_WEBSITE') || $yes('HIDE_FROM_SEARCH');
            $env = $yes('LOCAL_WEBSITE') ? AppEnv::Local : ($yes('HIDE_FROM_SEARCH') ? AppEnv::Staging : AppEnv::Production);
        }
        $env ??= AppEnv::Production;

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
            $env === AppEnv::Staging || $yes('HIDE_FROM_SEARCH'),
            $text('TIMEZONE', 'UTC'),
            $text('MAIL_FROM'),
            $text('VISIT_REPORT_TO'),
            ctype_digit($text('VISIT_EVENTS_MONTHS')) && (int) $text('VISIT_EVENTS_MONTHS') > 0 ? (int) $text('VISIT_EVENTS_MONTHS') : null,
            $text('GOOGLE_CLIENT_ID'),
            $text('GOOGLE_CLIENT_SECRET'),
            $text('BACKUP_FOLDER', self::DEFAULT_BACKUP_FOLDER),
            ctype_digit($text('BACKUPS_KEPT')) && (int) $text('BACKUPS_KEPT') > 0 ? (int) $text('BACKUPS_KEPT') : self::DEFAULT_BACKUPS_KEPT,
            $text('BACKUP_KEY'),
            in_array(strtolower($text('BACKUPS')), ['off', 'no', 'false', '0'], true),
            $text('ALERTS_TO'),
            $text('SMTP_HOST'),
            ctype_digit($text('SMTP_PORT')) && (int) $text('SMTP_PORT') > 0 ? (int) $text('SMTP_PORT') : 465,
            $text('SMTP_USER', $text('MAIL_FROM')),
            is_string($environment['SMTP_PASSWORD'] ?? null) ? $environment['SMTP_PASSWORD'] : '',
            $env,
            $text('APP_ENV'),
            $fromOld,
        );
    }

    /** A full address for a path on this website, or the path alone before install. */
    public function link(string $path): string
    {
        return $this->url . $path;
    }
}
