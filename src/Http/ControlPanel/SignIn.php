<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\User;
use SlimKiller\Http\Session\Session;

/**
 * The last step of every sign-in, once the password and the second factor have both
 * been checked: a fresh session token, and a line in the audit log.
 */
final class SignIn
{
    public const string PENDING = 'pending_user_id';

    public const string PENDING_SECRET = 'pending_totp_secret';

    public function __construct(
        private readonly AuditLog $audit,
    ) {}

    public function complete(Session $session, User $user, string $ip): void
    {
        $session->regenerate();
        $session->remove(self::PENDING, self::PENDING_SECRET);
        $session->set(Session::USER, $user->id);
        $this->audit->record('login', $user->id, $ip);
    }
}
