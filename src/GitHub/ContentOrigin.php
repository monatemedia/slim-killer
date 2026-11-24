<?php

declare(strict_types=1);

namespace SlimKiller\GitHub;

/**
 * Which GitHub repository a content folder came from, read from the git checkout it sits
 * in (its remote "origin"). Only for a working copy on a developer's computer, where
 * CONTENT_DIR points at a site: on a host the content comes from the build, fetched from
 * GITHUB_REPOSITORY itself, so the two can't differ.
 *
 * It exists for one mistake (found 23 November 2026): a local .env pointing at one
 * site's content and another site's repository, so a save would land in the wrong site.
 */
final class ContentOrigin
{
    /** How many folders up from the content folder to look for the checkout's .git. */
    private const int LEVELS = 3;

    /** "owner/name" of the checkout's origin on GitHub; null when there is no checkout, or it isn't on GitHub. */
    public static function repositoryOf(string $contentDir): ?string
    {
        $folder = rtrim($contentDir, '/\\');
        for ($level = 0; $level <= self::LEVELS && $folder !== '' && $folder !== dirname($folder); $level++) {
            $config = $folder . '/.git/config';
            if (is_file($config)) {
                return self::origin((string) file_get_contents($config));
            }
            $folder = dirname($folder);
        }

        return null;
    }

    /** True when both name the same repository; GitHub ignores case in names. */
    public static function same(string $one, string $other): bool
    {
        return strcasecmp(trim($one), trim($other)) === 0;
    }

    private static function origin(string $config): ?string
    {
        if (preg_match('/^\s*\[remote\s+"origin"\]\s*$(.*?)(?=^\s*\[|\z)/ms', $config, $section) !== 1) {
            return null;
        }
        if (preg_match('/^\s*url\s*=\s*(\S+)\s*$/m', $section[1], $url) !== 1) {
            return null;
        }

        return preg_match('#github\.com[:/]([\w.-]+)/([\w.-]+?)(?:\.git)?/?$#i', $url[1], $match) === 1
            ? $match[1] . '/' . $match[2]
            : null;
    }
}
