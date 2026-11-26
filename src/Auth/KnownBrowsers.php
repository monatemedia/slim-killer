<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * The browsers each person has signed in from. A browser carries a random value in a
 * long-lived cookie (sk_browser); a sign-in from a browser without it, or with one this
 * person hasn't used, is new, and they get an email (SignInNotice).
 *
 * Signing out keeps the cookie, so signing in again from the same browser stays quiet.
 * A browser not used for 400 days is forgotten, as browsers drop cookies that old.
 */
final class KnownBrowsers
{
    public const string COOKIE = 'sk_browser';

    public const int KEEP_SECONDS = 400 * 86400;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    /** A browser's cookie value, kept when it looks like one of ours, made when not. */
    public static function token(mixed $cookie): string
    {
        return is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie) === 1 ? $cookie : bin2hex(random_bytes(32));
    }

    /**
     * Notes that this person signed in from this browser.
     *
     * @return 'known'|'new'|'first' first when it is the person's first browser of all (the
     *                               end of setting up their account), which needs no email
     */
    public function signedIn(int $userId, string $token, string $name): string
    {
        $now = $this->clock->now()->getTimestamp();
        $hash = hash('sha256', $token);
        $this->pdo->prepare('DELETE FROM known_browsers WHERE last_seen < ?')->execute([$now - self::KEEP_SECONDS]);

        $seen = $this->pdo->prepare('UPDATE known_browsers SET last_seen = ?, name = ? WHERE user_id = ? AND token_hash = ?');
        $seen->execute([$now, $name, $userId, $hash]);
        if ($seen->rowCount() === 1) {
            return 'known';
        }

        $first = $this->count($userId) === 0;
        $this->pdo->prepare('INSERT INTO known_browsers (user_id, token_hash, name, first_seen, last_seen) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $hash, $name, $now, $now]);

        return $first ? 'first' : 'new';
    }

    public function count(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM known_browsers WHERE user_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }
}
