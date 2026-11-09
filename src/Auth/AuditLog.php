<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * What happened, to whom, from where: sign-ins, failed sign-ins, two-factor changes, resets.
 */
final class AuditLog
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    public function record(string $event, ?int $userId = null, string $ip = ''): void
    {
        $this->pdo->prepare('INSERT INTO audit_log (at, user_id, event, ip) VALUES (?, ?, ?, ?)')
            ->execute([$this->clock->now()->getTimestamp(), $userId, $event, $ip]);
    }

    /** @return list<string> The most recent events, newest first, for tests and support. */
    public function recentEvents(int $limit = 20): array
    {
        $statement = $this->pdo->query('SELECT event FROM audit_log ORDER BY id DESC LIMIT ' . $limit);

        return $statement === false ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
