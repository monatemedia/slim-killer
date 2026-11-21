<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

use DateTimeImmutable;
use SlimKiller\Support\Paths;

/**
 * The note that cron ran: when, with which PHP, and which command. `hammer doctor`
 * reads it back to check that cron runs every minute, with a new enough PHP, and runs
 * `cron:run` rather than the older `cron:heartbeat` line.
 */
final readonly class Heartbeat
{
    public const string FILE = 'cron-heartbeat.json';

    public function __construct(
        private Paths $paths,
    ) {}

    /** Returns false when the storage folder cannot be written. */
    public function record(DateTimeImmutable $at, string $command): bool
    {
        $file = $this->file();
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }

        return @file_put_contents($file, json_encode([
            'at' => $at->format(DATE_ATOM),
            'php_version' => PHP_VERSION,
            'php_binary' => PHP_BINARY,
            'command' => $command,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    }

    public function file(): string
    {
        return $this->paths->storage(self::FILE);
    }
}
