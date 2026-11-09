<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * Slows down guessing. Failed attempts are counted per key ("email:...", "ip:...",
 * "code:<user id>") over a rolling window; past the limit, that key waits.
 */
final class LoginThrottle
{
    public const int WINDOW_SECONDS = 15 * 60;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    /**
     * @param array<string, int> $limits Key => the most failures allowed in the window.
     * @return int Seconds to wait before trying again; 0 when no key is over its limit.
     */
    public function waitFor(array $limits): int
    {
        $now = $this->clock->now()->getTimestamp();
        $wait = 0;

        foreach ($limits as $key => $max) {
            $statement = $this->pdo->prepare('SELECT at FROM login_attempts WHERE key = ? AND at > ? ORDER BY at DESC LIMIT ' . (int) $max);
            $statement->execute([$key, $now - self::WINDOW_SECONDS]);
            $times = $statement->fetchAll(PDO::FETCH_COLUMN);

            if (count($times) >= $max) {
                $oldest = (int) end($times);
                $wait = max($wait, $oldest + self::WINDOW_SECONDS - $now);
            }
        }

        return $wait;
    }

    public function fail(string ...$keys): void
    {
        $now = $this->clock->now()->getTimestamp();
        $insert = $this->pdo->prepare('INSERT INTO login_attempts (key, at) VALUES (?, ?)');
        foreach ($keys as $key) {
            $insert->execute([$key, $now]);
        }

        $this->pdo->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([$now - 86400]);
    }

    public function clear(string ...$keys): void
    {
        $delete = $this->pdo->prepare('DELETE FROM login_attempts WHERE key = ?');
        foreach ($keys as $key) {
            $delete->execute([$key]);
        }
    }
}
