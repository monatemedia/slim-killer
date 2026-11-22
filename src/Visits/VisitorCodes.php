<?php

declare(strict_types=1);

namespace SlimKiller\Visits;

use PDO;

/**
 * Makes the visitor code that ties one visitor's events together for one day, without
 * a cookie and without keeping their internet address.
 *
 * The code is a hash of the internet address, the browser's description and the site,
 * keyed with a secret made fresh for each day. When the day is over the secret is
 * deleted, so the codes can no longer be worked out again, and the same visitor gets
 * a different code tomorrow.
 */
final readonly class VisitorCodes
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function codeFor(string $day, string $address, string $userAgent, string $host): string
    {
        return substr(hash_hmac('sha256', $address . "\n" . $userAgent . "\n" . strtolower($host), $this->secretFor($day)), 0, 16);
    }

    /** Deletes the secrets of the days before $day. Returns how many were deleted. */
    public function forgetBefore(string $day): int
    {
        $statement = $this->pdo->prepare('DELETE FROM visitor_secrets WHERE day < ?');
        $statement->execute([$day]);

        return $statement->rowCount();
    }

    private function secretFor(string $day): string
    {
        // Two visitors at the same instant: the first secret wins, and both read it back.
        $this->pdo->prepare('INSERT OR IGNORE INTO visitor_secrets (day, secret) VALUES (?, ?)')
            ->execute([$day, bin2hex(random_bytes(32))]);
        $statement = $this->pdo->prepare('SELECT secret FROM visitor_secrets WHERE day = ?');
        $statement->execute([$day]);

        return (string) $statement->fetchColumn();
    }
}
