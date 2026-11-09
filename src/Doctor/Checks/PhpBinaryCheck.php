<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\BinaryFinder;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Doctor\PhpBinary;
use SlimKiller\Doctor\Requirements;

/**
 * Which PHP to use for cron and hammer. On many shared hosts the plain `php` command is
 * an older version than the website uses, so cron must name the binary in full.
 */
final class PhpBinaryCheck implements HostCheck
{
    public function __construct(
        private readonly Environment $commandLine,
        private readonly BinaryFinder $finder,
    ) {}

    public function check(): array
    {
        $results = [];
        $current = $this->commandLine->phpBinary();

        if (version_compare($this->commandLine->phpVersion(), Requirements::MIN_PHP, '>=')) {
            $results[] = CheckResult::pass(
                'PHP for cron and hammer',
                $current . ' (PHP ' . $this->commandLine->phpVersion() . ')',
            );
        } else {
            $best = $this->finder->best(Requirements::MIN_PHP);
            $results[] = $best === null
                ? CheckResult::fail(
                    'PHP for cron and hammer',
                    'no PHP ' . Requirements::MIN_PHP_LABEL . ' or newer found',
                    'Ask your host how to run PHP ' . Requirements::MIN_PHP_LABEL . ' on the command line.',
                )
                : CheckResult::warn(
                    'PHP for cron and hammer',
                    'use ' . $best->path . ' (PHP ' . $best->version . ')',
                    'The default php command is older. Always call this path in full, in cron too.',
                );
        }

        $versions = array_map(static fn(PhpBinary $binary): string => $binary->version, $this->finder->find());
        if ($versions !== []) {
            $results[] = CheckResult::info('PHP versions on this server', implode(', ', array_unique($versions)));
        }

        return $results;
    }
}
