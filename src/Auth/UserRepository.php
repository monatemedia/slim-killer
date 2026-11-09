<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Database\Database;
use SlimKiller\Support\Clock;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    /** Is there anyone at all? False before `hammer install` has run. */
    public function any(): bool
    {
        if (! Database::hasTable($this->pdo, 'users')) {
            return false;
        }

        $statement = $this->pdo->query('SELECT 1 FROM users LIMIT 1');

        return $statement !== false && $statement->fetchColumn() !== false;
    }

    /** @throws UserAlreadyExists */
    public function create(EmailAddress $email, string $name, Role $role): User
    {
        if ($this->byEmail($email->value) !== null) {
            throw new UserAlreadyExists($email->value);
        }

        $this->pdo->prepare('INSERT INTO users (email, name, role, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$email->value, trim($name), $role->value, $this->clock->now()->getTimestamp()]);

        return new User((int) $this->pdo->lastInsertId(), $email->value, trim($name), $role, null, null, null);
    }

    public function byEmail(string $email): ?User
    {
        return $this->one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    public function byId(int $id): ?User
    {
        return $this->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }

    public function enableTwoFactor(int $id, string $secret, int $counter): void
    {
        $this->pdo->prepare('UPDATE users SET totp_secret = ?, totp_last_counter = ? WHERE id = ?')
            ->execute([$secret, $counter, $id]);
    }

    /** Remembers the last code used, so the same code cannot be used twice. */
    public function recordTotpCounter(int $id, int $counter): void
    {
        $this->pdo->prepare('UPDATE users SET totp_last_counter = ? WHERE id = ?')->execute([$counter, $id]);
    }

    /** Back to the start: no password, no two-factor, no recovery codes, signed out everywhere. */
    public function resetCredentials(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = NULL, totp_secret = NULL, totp_last_counter = NULL WHERE id = ?')
            ->execute([$id]);
        $this->pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]);
    }

    /** @param list<scalar> $parameters */
    private function one(string $sql, array $parameters): ?User
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        if (! is_array($row)) {
            return null;
        }

        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['name'],
            Role::from((string) $row['role']),
            $row['password_hash'] === null ? null : (string) $row['password_hash'],
            $row['totp_secret'] === null ? null : (string) $row['totp_secret'],
            $row['totp_last_counter'] === null ? null : (int) $row['totp_last_counter'],
        );
    }
}
