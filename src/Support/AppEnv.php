<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Where this Slim Killer runs (APP_ENV in .env), which decides how strict it is:
 *
 * - local: a developer's own computer. The built site is served at the root (on a .test
 *   or localhost address), email is saved to storage/mail instead of sent, and the
 *   doctor only informs.
 * - staging: a real host, showing the client a site that isn't finished. Search engines
 *   are asked not to list it, the panel and the website bar say "Staging", and the
 *   doctor warns.
 * - production: the live website. Listed by search engines, and the doctor fails on
 *   anything a live site needs: cron, backups, someone to alert.
 *
 * Before APP_ENV, LOCAL_WEBSITE and HIDE_FROM_SEARCH did part of this; they still work
 * when APP_ENV is empty, and the doctor says to move to APP_ENV.
 */
enum AppEnv: string
{
    case Local = 'local';
    case Staging = 'staging';
    case Production = 'production';

    /** APP_ENV as written, with a few everyday words for each; null when it is none of them. */
    public static function fromSetting(string $setting): ?self
    {
        return match (strtolower(trim($setting))) {
            'local', 'dev', 'development' => self::Local,
            'staging', 'stage', 'test', 'testing' => self::Staging,
            'production', 'prod', 'live' => self::Production,
            default => null,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
