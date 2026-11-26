<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Every new password goes through here: from a welcome link, a "Forgot your
 * password?" link, or Preferences. It must be long enough (PasswordPolicy), not one of
 * this person's last three (PasswordHistory), and not found in a data breach
 * (BreachedPasswords), checked in that order so a typo costs no request.
 */
final readonly class NewPassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private PasswordHistory $history,
        private BreachedPasswords $breaches,
        private AuditLog $audit,
    ) {}

    /** What is wrong with the new password, in plain words, or null if nothing is. */
    public function problem(User $user, string $password, string $confirmation, string $ip): ?string
    {
        $problem = PasswordPolicy::problem($password, $confirmation);
        if ($problem !== null) {
            return $problem;
        }

        if ($this->history->usedBefore($user->id, $user->passwordHash, $password)) {
            return 'You have used this password before. Choose one that isn\'t among your last ' . PasswordHistory::KEPT . '.';
        }

        $timesSeen = $this->breaches->timesSeen($password);
        if ($timesSeen === null) {
            $this->audit->record('breach_check_skipped', $user->id, $ip);
        } elseif ($timesSeen > 0) {
            return PasswordPolicy::breached($timesSeen);
        }

        return null;
    }

    public function set(User $user, string $password): void
    {
        $hash = $this->hasher->hash($password);
        $this->users->setPassword($user->id, $hash);
        $this->history->remember($user->id, $hash);
    }
}
