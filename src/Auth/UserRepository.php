<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use PDO;
use SlimKiller\Database\Database;
use SlimKiller\Support\Clock;
use SlimKiller\Visits\ReportSchedule;

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

        // The emails a role starts with: administrators hear when something stops working,
        // site owners get the visit report. Each person changes theirs in Preferences.
        $visitReport = $role === Role::Owner ? ReportSchedule::DEFAULT_FOR_OWNERS : '';
        $alerts = $role === Role::Admin;
        $this->pdo->prepare('INSERT INTO users (email, name, role, created_at, visit_report, alerts) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$email->value, trim($name), $role->value, $this->clock->now()->getTimestamp(), $visitReport, $alerts ? 1 : 0]);

        return new User((int) $this->pdo->lastInsertId(), $email->value, trim($name), $role, null, null, null, WebsiteBar::Show, $visitReport, $alerts);
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
    public function setWebsiteBar(int $id, WebsiteBar $bar): void
    {
        $this->pdo->prepare('UPDATE users SET website_bar = ? WHERE id = ?')->execute([$bar->value, $id]);
    }

    public function recordTotpCounter(int $id, int $counter): void
    {
        $this->pdo->prepare('UPDATE users SET totp_last_counter = ? WHERE id = ?')->execute([$counter, $id]);
    }

    /** Back to the start: no password, no two-factor, no recovery codes, no known browsers, signed out everywhere. */
    public function resetCredentials(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = NULL, totp_secret = NULL, totp_last_counter = NULL WHERE id = ?')
            ->execute([$id]);
        $this->pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]);
        // Their browsers are forgotten too: after setting up again, a sign-in from any
        // other browser is emailed.
        $this->pdo->prepare('DELETE FROM known_browsers WHERE user_id = ?')->execute([$id]);
    }

    public function setEmails(int $id, string $visitReport, bool $alerts): void
    {
        $this->pdo->prepare('UPDATE users SET visit_report = ?, alerts = ? WHERE id = ?')->execute([$visitReport, $alerts ? 1 : 0, $id]);
    }

    /**
     * Everyone who has finished setting up their account and chose to be emailed when
     * something stops working. Empty before `hammer migrate` has added the choice.
     *
     * @return list<string>
     */
    public function alertAddresses(): array
    {
        if (! Database::hasTable($this->pdo, 'visit_report_sends')) {
            return [];
        }
        $statement = $this->pdo->query('SELECT email FROM users WHERE alerts = 1 AND totp_secret IS NOT NULL ORDER BY id');

        return $statement === false ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<User> Everyone set up who chose a visit report. */
    public function visitReportReaders(): array
    {
        if (! Database::hasTable($this->pdo, 'visit_report_sends')) {
            return [];
        }

        return array_values(array_filter($this->all(), static fn(User $user): bool => $user->visitReport !== '' && $user->hasTwoFactor()));
    }

    /** @return list<User> Everyone, administrators first, then by name. */
    public function all(): array
    {
        $statement = $this->pdo->query("SELECT * FROM users ORDER BY role = 'admin' DESC, name COLLATE NOCASE, id");
        $users = [];
        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            if (is_array($row)) {
                $users[] = self::fromRow($row);
            }
        }

        return $users;
    }

    /** @return array<int, int> When each person last signed in (a Unix timestamp), by user id. */
    public function lastSignIns(): array
    {
        $statement = $this->pdo->query("SELECT user_id, MAX(at) FROM audit_log WHERE event = 'sign_in' AND user_id IS NOT NULL GROUP BY user_id");
        $last = [];
        foreach ($statement === false ? [] : $statement->fetchAll(PDO::FETCH_NUM) as $row) {
            $last[(int) $row[0]] = (int) $row[1];
        }

        return $last;
    }

    /** Signs the person out everywhere but this session's cookie (when one is given). */
    public function signOutEverywhere(int $id, ?string $exceptToken = null): void
    {
        // Sessions are stored under a hash of their cookie (SessionStore).
        $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id <> ?')
            ->execute([$id, $exceptToken === null ? '' : hash('sha256', $exceptToken)]);
    }

    /** Gone: their sessions, browsers, codes and links with them. The audit log keeps what they did. */
    public function delete(int $id): void
    {
        foreach (['sessions', 'known_browsers', 'recovery_codes', 'welcome_links', 'password_resets', 'password_history'] as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$id]);
        }
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    /** @param list<scalar> $parameters */
    private function one(string $sql, array $parameters): ?User
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? self::fromRow($row) : null;
    }

    /** @param array<mixed> $row */
    private static function fromRow(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['name'],
            Role::from((string) $row['role']),
            $row['password_hash'] === null ? null : (string) $row['password_hash'],
            $row['totp_secret'] === null ? null : (string) $row['totp_secret'],
            $row['totp_last_counter'] === null ? null : (int) $row['totp_last_counter'],
            WebsiteBar::tryFrom((string) ($row['website_bar'] ?? '')) ?? WebsiteBar::Show,
            (string) ($row['visit_report'] ?? ''),
            (int) ($row['alerts'] ?? 0) === 1,
        );
    }
}
