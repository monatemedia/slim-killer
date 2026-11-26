<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * "Forgot your password?" links. Emailed to the account's own address, valid for an
 * hour, once; a newer link replaces an older one. Only a hash is stored. Choosing a new
 * password through one still leads to the two-factor step: an email alone never signs
 * anyone in.
 */
final class PasswordResetLinks
{
    public const string PATH = '/cp/reset-password';

    public const int LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    /** @return string The secret token for the link. */
    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(20));
        $this->pdo->prepare('INSERT OR REPLACE INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$userId, hash('sha256', $token), $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS]);

        return $token;
    }

    public static function pathFor(string $token): string
    {
        return self::PATH . '?token=' . rawurlencode($token);
    }

    /** The user the link belongs to, while it is still valid. */
    public function userFor(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > ?');
        $statement->execute([hash('sha256', $token), $this->clock->now()->getTimestamp()]);
        $userId = $statement->fetchColumn();

        return $userId === false ? null : (int) $userId;
    }

    public function isOpenFor(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM password_resets WHERE user_id = ? AND expires_at > ?');
        $statement->execute([$userId, $this->clock->now()->getTimestamp()]);

        return $statement->fetchColumn() !== false;
    }

    public function close(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    }
}
