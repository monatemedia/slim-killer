<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Support\Clock;

/**
 * Single-use codes for when the phone with the authenticator app is lost. Shown once,
 * when two-factor is set up; only hashes are stored.
 */
final class RecoveryCodes
{
    public const int COUNT = 8;

    /** No 0/O or 1/l/i, so a code copied from paper is read correctly. */
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {}

    /**
     * Replaces any earlier codes with a new set.
     *
     * @return list<string> The codes, to show to the person once.
     */
    public function replace(int $userId): array
    {
        $this->pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$userId]);
        $insert = $this->pdo->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (?, ?)');

        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $code = $this->randomCode();
            $insert->execute([$userId, self::hash($code)]);
            $codes[] = $code;
        }

        return $codes;
    }

    /** Uses up a code. Returns false if it is wrong or was used before. */
    public function use(int $userId, string $code): bool
    {
        $statement = $this->pdo->prepare('UPDATE recovery_codes SET used_at = ? WHERE user_id = ? AND code_hash = ? AND used_at IS NULL');
        $statement->execute([$this->clock->now()->getTimestamp(), $userId, self::hash($code)]);

        return $statement->rowCount() === 1;
    }

    public function remaining(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /** Does this look like a recovery code rather than a six-digit code? */
    public static function looksLikeOne(string $input): bool
    {
        return preg_match('/^[a-z0-9]{5}-?[a-z0-9]{5}$/', strtolower(trim($input))) === 1;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', str_replace('-', '', strtolower(trim($code))));
    }

    private function randomCode(): string
    {
        $characters = '';
        for ($i = 0; $i < 10; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return substr($characters, 0, 5) . '-' . substr($characters, 5);
    }
}
