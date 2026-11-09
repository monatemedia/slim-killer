<?php

declare(strict_types=1);

namespace SlimKiller\Http\Session;

use PDO;
use SlimKiller\Database\Database;
use SlimKiller\Support\Clock;

/**
 * Sessions in SQLite. A session ends after two hours without a request, and after
 * twelve hours whatever happens, so a forgotten browser tab does not stay signed in.
 */
final class SessionStore
{
    public const int IDLE_SECONDS = 2 * 3600;

    public const int MAX_SECONDS = 12 * 3600;

    private ?bool $ready = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    public function load(string $token): ?Session
    {
        if (! $this->ready()) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();
        $statement = $this->pdo->prepare('SELECT data, created_at FROM sessions WHERE id = ? AND expires_at > ? AND created_at > ?');
        $statement->execute([self::id($token), $now, $now - self::MAX_SECONDS]);
        $row = $statement->fetch();

        if (! is_array($row)) {
            return null;
        }

        $decoded = json_decode((string) $row['data'], true);
        $data = [];
        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $data[$key] = $value;
            }
        }

        return Session::resume($token, $data, (int) $row['created_at']);
    }

    public function save(Session $session): void
    {
        if (! $this->ready()) {
            return;
        }

        $previous = $session->previousToken();
        if ($previous !== null) {
            $this->delete($previous);
        }

        $this->pdo->prepare('INSERT OR REPLACE INTO sessions (id, user_id, data, created_at, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                self::id($session->token()),
                $session->userId(),
                json_encode($session->data(), JSON_THROW_ON_ERROR),
                $session->createdAt,
                $this->clock->now()->getTimestamp() + self::IDLE_SECONDS,
            ]);

        $this->pdo->prepare('DELETE FROM sessions WHERE expires_at < ?')->execute([$this->clock->now()->getTimestamp()]);
    }

    public function delete(string $token): void
    {
        $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([self::id($token)]);
    }

    /** Before `hammer install` there is no sessions table, and nothing to keep. */
    private function ready(): bool
    {
        return $this->ready ??= Database::hasTable($this->pdo, 'sessions');
    }

    private static function id(string $token): string
    {
        return hash('sha256', $token);
    }
}
