<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * A number of seconds as people say it: "40 seconds", "12 minutes", "3 hours".
 */
final class Ago
{
    public static function seconds(int $seconds): string
    {
        return match (true) {
            $seconds < 120 => max(0, $seconds) . ' seconds',
            $seconds < 7200 => intdiv($seconds, 60) . ' minutes',
            $seconds < 172800 => intdiv($seconds, 3600) . ' hours',
            default => intdiv($seconds, 86400) . ' days',
        };
    }
}
