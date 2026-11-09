<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

enum Role: string
{
    /** The client: edits their own site. */
    case Owner = 'owner';

    /** Monate Media: looks after the site, updates and recovery. */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'site owner',
            self::Admin => 'administrator',
        };
    }
}
