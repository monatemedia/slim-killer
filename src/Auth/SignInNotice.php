<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use DateTimeImmutable;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Hammer;
use SlimKiller\Visits\SiteTime;

/**
 * The email someone gets when their account signs in from a browser it hasn't used
 * before, or with a recovery code instead of the phone. It goes to that person only.
 * If it wasn't them, they know at once and can ask for their account to be reset.
 */
final readonly class SignInNotice
{
    public function __construct(
        private LetterWriter $writer,
        private Mailer $mailer,
        private AppConfig $config,
        private SiteTime $time,
    ) {}

    public function canSend(): bool
    {
        return $this->config->mailFrom !== '';
    }

    /** @param int|null $codesLeft the recovery codes left, when one was used */
    public function send(User $user, string $browser, string $ip, DateTimeImmutable $at, bool $newBrowser, ?int $codesLeft): bool
    {
        if (! $this->canSend()) {
            return false;
        }
        $subject = $this->config->name . ': ' . ($codesLeft !== null
            ? 'a recovery code was used to sign in'
            : 'a new sign-in from ' . (preg_match('/^An? /', $browser) === 1 ? lcfirst($browser) : $browser));

        return $this->mailer->send($this->writer->write('sign-in', $user->email, $subject, [
            'name' => $user->name,
            'email' => $user->email,
            'browser' => $browser,
            'ip' => $ip,
            'when' => $at->setTimezone($this->time->zone)->format('j F Y, H:i'),
            'new_browser' => $newBrowser,
            'recovery' => $codesLeft !== null,
            'codes_left' => $codesLeft ?? 0,
            'reset' => Hammer::command('user:reset --email=' . $user->email),
            'preview' => $codesLeft !== null ? 'Your phone wasn\'t used for this sign-in.' : 'If this was you, there is nothing to do.',
        ]));
    }
}
