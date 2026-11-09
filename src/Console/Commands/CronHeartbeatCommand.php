<?php

declare(strict_types=1);

namespace SlimKiller\Console\Commands;

use SlimKiller\Console\Command;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * Records that cron ran, and with which PHP. `hammer doctor` reads it back.
 *
 * Temporary: once `jobs:run` exists, cron calls that instead and it records the
 * heartbeat itself.
 */
final class CronHeartbeatCommand implements Command
{
    public const string FILE = 'cron-heartbeat.json';

    public function __construct(
        private readonly Paths $paths,
        private readonly Clock $clock,
    ) {}

    public function name(): string
    {
        return 'cron:heartbeat';
    }

    public function description(): string
    {
        return 'Record that cron ran (add this to cron, every minute)';
    }

    public function run(Input $input, Output $output): int
    {
        $file = $this->paths->storage(self::FILE);
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        $written = @file_put_contents($file, json_encode([
            'at' => $this->clock->now()->format(DATE_ATOM),
            'php_version' => PHP_VERSION,
            'php_binary' => PHP_BINARY,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);

        if ($written === false) {
            $output->line("Could not write {$file}. Is the storage folder writable?", 'red');

            return 1;
        }

        return 0;
    }
}
