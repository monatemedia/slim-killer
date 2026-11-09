<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * A file's version, the way Git and GitHub name it: the SHA-1 of "blob <size>\0<bytes>".
 * The working copy records it for every file it reads, so a save can tell GitHub which
 * version it started from, and refuse if someone saved a newer one since.
 */
final class GitBlob
{
    public static function hash(string $bytes): string
    {
        return sha1('blob ' . strlen($bytes) . "\0" . $bytes);
    }
}
