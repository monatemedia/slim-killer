<?php

declare(strict_types=1);

namespace SlimKiller\Setup;

use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * The private link to the control panel's setup page, which shows the full host report.
 *
 * Only someone who can run `hammer` on the server can get one: the doctor issues a
 * new link at the end of every run. The link works for one hour, and each new link
 * replaces the last. Only a hash of the token is stored, so reading the storage
 * folder does not reveal a working link.
 */
final class SetupLink
{
    public const string FILE = 'setup-link.json';

    public const string PATH = '/cp/setup';

    public const int LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly Paths $paths,
        private readonly Clock $clock,
    ) {}

    /**
     * Creates a new link, replacing any earlier one, and returns its secret token.
     *
     * @throws SetupLinkNotSaved
     */
    public function issue(): string
    {
        $token = bin2hex(random_bytes(16));
        $file = $this->paths->storage(self::FILE);

        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }

        $json = json_encode([
            'hash' => hash('sha256', $token),
            'expires' => $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS,
        ], JSON_THROW_ON_ERROR);

        if (@file_put_contents($file, $json, LOCK_EX) === false) {
            throw new SetupLinkNotSaved($file);
        }

        return $token;
    }

    /** The path and query to open, relative to the website's address. */
    public static function pathFor(string $token): string
    {
        return self::PATH . '?token=' . rawurlencode($token);
    }

    public function accepts(string $token): bool
    {
        $file = $this->paths->storage(self::FILE);
        if ($token === '' || ! is_file($file)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return false;
        }

        $hash = $data['hash'] ?? null;
        $expires = $data['expires'] ?? null;
        if (! is_string($hash) || ! is_int($expires)) {
            return false;
        }

        if ($this->clock->now()->getTimestamp() >= $expires) {
            return false;
        }

        return hash_equals($hash, hash('sha256', $token));
    }
}
