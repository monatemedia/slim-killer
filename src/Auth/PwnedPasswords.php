<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use SlimKiller\Http\HttpClient;

/**
 * Have I Been Pwned's Pwned Passwords, asked the safe way (k-anonymity): only the first
 * five characters of the password's SHA-1 hash leave the server, never the password or
 * its whole hash. The service answers with every hash that starts the same way, and
 * the match is found here. Padding makes every answer about the same size, so even the
 * size of the reply says nothing.
 *
 * If the service can't be reached, the check is skipped (null) rather than locking
 * anyone out of setting a password; the other rules still apply.
 */
final class PwnedPasswords implements BreachedPasswords
{
    public const string RANGE_URL = 'https://api.pwnedpasswords.com/range/';

    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function timesSeen(string $password): ?int
    {
        $hash = strtoupper(sha1($password));
        $response = $this->http->get(self::RANGE_URL . substr($hash, 0, 5), ['Add-Padding' => 'true'], self::TIMEOUT_SECONDS);
        if (! $response->ok()) {
            return null;
        }

        $suffix = substr($hash, 5);
        foreach (preg_split('/\R/', $response->body) ?: [] as $line) {
            [$candidate, $count] = array_pad(explode(':', trim($line), 2), 2, '0');
            if (strtoupper($candidate) === $suffix) {
                return (int) $count;
            }
        }

        return 0;
    }
}
