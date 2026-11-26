<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * Each person's last three passwords (hashes only), so a new password can't be one
 * of them. A reset clears the password but not this, so a reset doesn't let an old
 * password back in either.
 */
final class PasswordHistory
{
    public const int KEPT = 3;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
        private readonly PasswordHasher $hasher,
    ) {}

    /** Is this one of the person's last three passwords, the current one included? */
    public function usedBefore(int $userId, ?string $currentHash, string $password): bool
    {
        $statement = $this->pdo->prepare('SELECT hash FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . self::KEPT);
        $statement->execute([$userId]);
        $hashes = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if ($currentHash !== null) {
            $hashes[] = $currentHash; // Set before this history began.
        }

        foreach (array_unique($hashes) as $hash) {
            if ($this->hasher->verify($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function remember(int $userId, string $hash): void
    {
        $this->pdo->prepare('INSERT INTO password_history (user_id, hash, set_at) VALUES (?, ?, ?)')
            ->execute([$userId, $hash, $this->clock->now()->getTimestamp()]);
        $this->pdo->prepare('DELETE FROM password_history WHERE user_id = ? AND id NOT IN (SELECT id FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . self::KEPT . ')')
            ->execute([$userId, $userId]);
    }
}
