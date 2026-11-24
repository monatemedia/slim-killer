<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * How to run a hammer command on this server, for every hint and message that names one:
 * "/opt/alt/php84/usr/bin/php hammer backup:connect", not a bare "hammer backup:connect",
 * which a shell doesn't know. Run from the slim-killer folder.
 *
 * The PHP is set once, at the entry point (BUILD_PHILOSOPHY.md, Rule 1): hammer uses the
 * PHP it was started with; the control panel and the website use the one cron runs with,
 * from cron's last note. Until then, "php".
 */
final class Hammer
{
    private static string $php = 'php';

    public static function usePhp(string $binary): void
    {
        if (trim($binary) !== '') {
            self::$php = trim($binary);
        }
    }

    /** The PHP cron last ran with, from its note in storage; nothing when cron hasn't run. */
    public static function usePhpFromHeartbeat(string $heartbeatFile): void
    {
        $data = is_file($heartbeatFile) ? json_decode((string) file_get_contents($heartbeatFile), true) : null;
        if (is_array($data) && is_string($data['php_binary'] ?? null)) {
            self::usePhp($data['php_binary']);
        }
    }

    /** The whole command line, like "/opt/alt/php84/usr/bin/php hammer backup". */
    public static function command(string $arguments): string
    {
        return self::$php . ' hammer' . ($arguments !== '' ? ' ' . $arguments : '');
    }
}
