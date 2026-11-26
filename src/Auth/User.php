<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

/**
 * Someone who can use the control panel. A user without a password has a welcome
 * link waiting; a user without two-factor has not finished setting up, and cannot
 * get past the two-factor step.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public Role $role,
        public ?string $passwordHash,
        public ?string $totpSecret,
        public ?int $totpLastCounter,
        public WebsiteBar $websiteBar = WebsiteBar::Show,
        /** When the visit report comes, cron-style (ReportSchedule); empty: never. */
        public string $visitReport = '',
        /** Emailed when something stops working (Alerts)? */
        public bool $alerts = false,
    ) {}

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->totpSecret !== null;
    }

    /**
     * Can this person add, reset or remove that one from the People page? Administrators
     * can for anyone, site owners for site owners. Nobody for themself: their own
     * password is on Preferences.
     */
    public function canManage(self $other): bool
    {
        return $other->id !== $this->id && $this->canAdd($other->role);
    }

    /** Can this person add someone with that role? */
    public function canAdd(Role $role): bool
    {
        return $this->role === Role::Admin || $role === Role::Owner;
    }
}
