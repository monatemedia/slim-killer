<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * A browser's name a person recognises, like "Safari on an iPhone" or "Chrome on
 * Windows", from its User-Agent. Only for the sign-in email: never trusted for anything.
 */
final class BrowserName
{
    /** Checked in order: Edge and Opera also say "Chrome", Chrome also says "Safari". */
    private const array BROWSERS = [
        'Edg' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'FxiOS' => 'Firefox',
        'Firefox/' => 'Firefox',
        'CriOS' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const array SYSTEMS = [
        'iPhone' => 'an iPhone',
        'iPad' => 'an iPad',
        'Android' => 'Android',
        'CrOS' => 'a Chromebook',
        'Windows' => 'Windows',
        'Macintosh' => 'a Mac',
        'Linux' => 'Linux',
    ];

    public static function from(string $userAgent): string
    {
        $browser = self::first(self::BROWSERS, $userAgent);
        $system = self::first(self::SYSTEMS, $userAgent);

        return match (true) {
            $browser !== null && $system !== null => "{$browser} on {$system}",
            $browser !== null => $browser,
            $system !== null => "A browser on {$system}",
            default => 'An unknown browser',
        };
    }

    /** @param array<string, string> $names */
    private static function first(array $names, string $userAgent): ?string
    {
        foreach ($names as $marker => $name) {
            if (str_contains($userAgent, $marker)) {
                return $name;
            }
        }

        return null;
    }
}
