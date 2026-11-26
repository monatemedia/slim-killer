<?php

declare(strict_types=1);

namespace SlimKiller\Auth;

use DateTimeImmutable;
use SlimKiller\Mail\LetterWriter;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\SiteTime;

/**
 * The emails about someone's own account: their welcome link when they are added or
 * reset from the People page, a "Forgot your password?" link, and a note when their
 * password or two-factor changes. Each goes to that person only. Without MAIL_FROM, none is sent.
 */
final readonly class AccountMail
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

    /** @param bool $reset true when an existing account was reset, false when it is new */
    public function welcomeLink(User $person, User $by, string $link, bool $reset): bool
    {
        return $this->send('welcome-link', $person, $reset ? 'your account was reset' : 'your account on the control panel', [
            'by' => $by->name,
            'link' => $link,
            'reset' => $reset,
            'hours' => intdiv(WelcomeLinks::LIFETIME_SECONDS, 3600),
            'preview' => 'Open the link to choose your password.',
        ]);
    }

    public function passwordReset(User $person, string $link): bool
    {
        return $this->send('password-reset', $person, 'choose a new password', [
            'link' => $link,
            'minutes' => intdiv(PasswordResetLinks::LIFETIME_SECONDS, 60),
            'preview' => 'The link works for an hour, once.',
        ]);
    }

    public function passwordChanged(User $person, string $browser, string $ip, DateTimeImmutable $at): bool
    {
        return $this->send('password-changed', $person, 'your password was changed', [
            'browser' => $browser,
            'ip' => $ip,
            'when' => $at->setTimezone($this->time->zone)->format('j F Y, H:i'),
            'preview' => 'If this was you, there is nothing to do.',
        ]);
    }

    /** @param 'codes'|'app' $what new recovery codes, or two-factor set up again */
    public function twoFactorChanged(User $person, string $what, string $browser, string $ip, DateTimeImmutable $at): bool
    {
        return $this->send('two-factor-changed', $person, $what === 'app' ? 'two-factor was set up again' : 'you have new recovery codes', [
            'what' => $what,
            'browser' => $browser,
            'ip' => $ip,
            'when' => $at->setTimezone($this->time->zone)->format('j F Y, H:i'),
            'preview' => 'If this was you, there is nothing to do.',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function send(string $template, User $person, string $subject, array $data): bool
    {
        if (! $this->canSend()) {
            return false;
        }

        return $this->mailer->send($this->writer->write($template, $person->email, $this->config->name . ': ' . $subject, [
            'name' => $person->name,
            'email' => $person->email,
            ...$data,
        ]));
    }
}
