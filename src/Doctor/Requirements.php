<?php

declare(strict_types=1);

namespace SlimKiller\Doctor;

/**
 * What Slim Killer needs from a host, in one place. The wiki page "Host requirements"
 * is written from this file; change them together.
 */
final class Requirements
{
    public const string MIN_PHP = '8.4.0';

    /** MIN_PHP as people write it. */
    public const string MIN_PHP_LABEL = '8.4';

    /** Extensions Slim Killer cannot work without, and why. */
    public const array REQUIRED_EXTENSIONS = [
        'curl' => 'talks to GitHub',
        'openssl' => 'secure connections and encryption',
        'json' => 'reads and writes JSON',
        'pdo_sqlite' => 'the control panel database',
        'zip' => 'unpacks site and control panel releases',
        'gd' => 'resizes and re-encodes uploaded photos',
        'exif' => 'turns photos from a phone the right way up',
        'mbstring' => 'handles text in any language',
        'fileinfo' => 'checks what an uploaded file really is',
    ];

    /** Extensions that help, and what Slim Killer does without them. */
    public const array RECOMMENDED_EXTENSIONS = [
        'sodium' => 'Slim Killer uses openssl for encryption instead',
        'intl' => 'dates and numbers fall back to plain formats',
    ];

    /** Functions that must not be disabled. */
    public const array REQUIRED_FUNCTIONS = [
        'curl_init', 'curl_exec', 'fopen', 'file_put_contents', 'rename', 'unlink',
        'mkdir', 'rmdir', 'flock', 'random_bytes', 'hash_hmac',
    ];

    /** Functions often disabled on shared hosting that Slim Killer never uses. */
    public const array NOT_NEEDED_FUNCTIONS = [
        'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'symlink',
    ];

    public const string MIN_MEMORY = '128M';

    /** Seconds. Fetching and unpacking a site took 2 to 3 s in testing; 30 leaves room. */
    public const int MIN_EXECUTION_TIME = 30;

    /** Photos straight from a phone are often 5 to 10 MB. */
    public const string MIN_UPLOAD = '10M';

    /** A cron heartbeat older than this means cron is not running every minute. */
    public const int CRON_STALE_AFTER_SECONDS = 180;
}
