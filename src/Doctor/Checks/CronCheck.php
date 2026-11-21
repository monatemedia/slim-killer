<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use DateTimeImmutable;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Doctor\Requirements;
use SlimKiller\Support\Ago;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * Cron cannot be set up from code: the host's control panel does that. This check
 * gives the exact line to add, then confirms on a later run that cron really fires,
 * with a new enough PHP, and runs `cron:run` rather than the older `cron:heartbeat`.
 */
final class CronCheck implements HostCheck
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Clock $clock,
        private readonly Environment $commandLine,
    ) {}

    public function check(): array
    {
        $line = $this->cronLine();
        $file = $this->paths->storage(Heartbeat::FILE);

        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data) || ! is_string($data['at'] ?? null)) {
            return [CheckResult::warn(
                'Cron has not run yet',
                'add this cron job, every minute (* * * * *):',
                $line,
            )];
        }

        $last = new DateTimeImmutable($data['at']);
        $seconds = $this->clock->now()->getTimestamp() - $last->getTimestamp();
        $version = is_string($data['php_version'] ?? null) ? $data['php_version'] : '?';

        if (version_compare($version, Requirements::MIN_PHP, '<')) {
            return [CheckResult::fail(
                'Cron runs an old PHP',
                'PHP ' . $version . ', needs ' . Requirements::MIN_PHP_LABEL,
                'Change the cron job to name the PHP binary in full: ' . $line,
            )];
        }

        if ($seconds > Requirements::CRON_STALE_AFTER_SECONDS) {
            return [CheckResult::warn(
                'Cron has stopped, or does not run every minute',
                'last ran ' . Ago::seconds($seconds) . ' ago',
                'Check the cron job: ' . $line,
            )];
        }

        // Heartbeats written before cron:run existed say nothing about the command.
        if (($data['command'] ?? 'cron:heartbeat') !== 'cron:run') {
            return [CheckResult::warn(
                'Cron runs the older line',
                'it notes that cron ran, but runs nothing else',
                'Change the cron job to: ' . $line . '. Then delete the deploy:fetch cron job: cron:run does that too.',
            )];
        }

        return [CheckResult::pass('Cron runs every minute', 'last ran ' . Ago::seconds($seconds) . ' ago, PHP ' . $version)];
    }

    private function cronLine(): string
    {
        return $this->commandLine->phpBinary() . ' ' . $this->paths->root('hammer') . ' cron:run';
    }
}
