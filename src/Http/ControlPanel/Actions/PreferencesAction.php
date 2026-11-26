<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AccountMail;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\BrowserName;
use SlimKiller\Auth\NewPassword;
use SlimKiller\Auth\PasswordHasher;
use SlimKiller\Auth\PasswordPolicy;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\SignInThrottle;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WebsiteBar;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\Clock;
use SlimKiller\Visits\ReportSchedule;
use SlimKiller\Visits\SiteTime;

/**
 * /cp/preferences: each person's own choices. Whether they see the website bar on the
 * website, which emails they get (the visit report, and alerts when something stops
 * working), their password, and their two-factor (new recovery codes, or a new app).
 *
 * Changing the password asks for the current one first, so a browser left signed in
 * can't be used to take the account. Every other device is then signed out, and the
 * person gets an email saying the password changed.
 */
final class PreferencesAction
{
    public const int MAX_PASSWORD_ATTEMPTS = 5;

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly NewPassword $newPassword,
        private readonly SignInThrottle $throttle,
        private readonly AccountMail $mail,
        private readonly AuditLog $audit,
        private readonly AfterResponse $afterResponse,
        private readonly Clock $clock,
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly SiteTime $time,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = SignedIn::user($request);
        $session = Session::of($request);

        if ($request->getMethod() === 'POST' && Form::field($request, 'form') === 'password') {
            return $this->changePassword($request, $response, $session, $user);
        }

        if ($request->getMethod() === 'POST' && Form::field($request, 'form') === 'emails') {
            $schedule = ReportSchedule::fromChoices(
                Form::field($request, 'frequency'),
                (int) Form::field($request, 'hour'),
                (int) Form::field($request, 'weekday'),
                (int) Form::field($request, 'date'),
            );
            $this->users->setEmails($user->id, $schedule->cron(), Form::field($request, 'alerts') === '1');

            return Form::redirect($response, '/preferences?saved=emails#emails');
        }

        if ($request->getMethod() === 'POST' && Form::field($request, 'form') === 'two-factor') {
            return $this->changeTwoFactor($request, $response, $session, $user);
        }

        if ($request->getMethod() === 'POST') {
            $bar = WebsiteBar::tryFrom(Form::field($request, 'website_bar')) ?? $user->websiteBar;
            $this->users->setWebsiteBar($user->id, $bar);

            return Form::redirect($response, '/preferences?saved=1');
        }

        return $this->page($response, $session, $user, null, 200, match (Form::query($request, 'saved')) {
            '1' => 'bar',
            'password' => 'password',
            'two-factor' => 'two-factor',
            'emails' => 'emails',
            default => null,
        });
    }

    private function changePassword(ServerRequestInterface $request, ResponseInterface $response, Session $session, User $user): ResponseInterface
    {
        $key = 'password:' . $user->id;
        $wait = $this->throttle->waitFor([$key => self::MAX_PASSWORD_ATTEMPTS]);
        if ($wait > 0) {
            return $this->page($response, $session, $user, 'Too many attempts. Try again in ' . Form::minutes($wait) . '.', 429);
        }

        $ip = Form::ip($request);
        if ($user->passwordHash === null || ! $this->hasher->verify(Form::field($request, 'current_password'), $user->passwordHash)) {
            $this->throttle->fail($key);
            $this->audit->record('password_change_failed', $user->id, $ip);

            return $this->page($response, $session, $user, 'That isn\'t your current password.', 422);
        }

        $password = Form::field($request, 'password');
        $problem = $this->newPassword->problem($user, $password, Form::field($request, 'password_confirmation'), $ip);
        if ($problem !== null) {
            return $this->page($response, $session, $user, $problem, 422);
        }

        $this->throttle->clear($key);
        $this->newPassword->set($user, $password);
        $this->users->signOutEverywhere($user->id, $session->token());
        $session->regenerate();
        $this->audit->record('password_changed', $user->id, $ip);
        $browser = BrowserName::from($request->getHeaderLine('User-Agent'));
        $at = $this->clock->now();
        $this->afterResponse->run(fn(): bool => $this->mail->passwordChanged($user, $browser, $ip, $at));

        return Form::redirect($response, '/preferences?saved=password');
    }

    /**
     * New recovery codes, or two-factor set up again with a new app. Both need the
     * password and a code from the app in use now, so neither a stolen password nor a
     * browser left signed in is enough. A lost phone needs a reset (People page) instead.
     */
    private function changeTwoFactor(ServerRequestInterface $request, ResponseInterface $response, Session $session, User $user): ResponseInterface
    {
        $key = 'two-factor:' . $user->id;
        $wait = $this->throttle->waitFor([$key => self::MAX_PASSWORD_ATTEMPTS]);
        if ($wait > 0) {
            return $this->page($response, $session, $user, null, 429, null, 'Too many attempts. Try again in ' . Form::minutes($wait) . '.');
        }

        $ip = Form::ip($request);
        $passwordRight = $user->passwordHash !== null && $this->hasher->verify(Form::field($request, 'two_factor_password'), $user->passwordHash);
        $counter = $user->totpSecret === null ? null : $this->totp->match($user->totpSecret, Form::field($request, 'code'), $user->totpLastCounter);
        if (! $passwordRight || $counter === null) {
            $this->throttle->fail($key);
            $this->audit->record('two_factor_change_failed', $user->id, $ip);

            return $this->page($response, $session, $user, null, 422, null, $passwordRight
                ? 'That code is not right. Type the newest code from the app you use now.'
                : 'That isn\'t your current password.');
        }

        $this->throttle->clear($key);
        $this->users->recordTotpCounter($user->id, $counter);

        if (Form::field($request, 'task') === 'app') {
            $session->set(TwoFactorChangeAction::SECRET, Totp::newSecret());
            $session->set(TwoFactorChangeAction::CONFIRMED_AT, $this->clock->now()->getTimestamp());

            return Form::redirect($response, '/two-factor/change');
        }

        $codes = $this->recoveryCodes->replace($user->id);
        $this->audit->record('recovery_codes_replaced', $user->id, $ip);
        $browser = BrowserName::from($request->getHeaderLine('User-Agent'));
        $at = $this->clock->now();
        $this->afterResponse->run(fn(): bool => $this->mail->twoFactorChanged($user, 'codes', $browser, $ip, $at));

        return $this->view->render($response, 'cp/recovery-codes.twig', [
            'codes' => $codes,
            'account' => $user->email,
            'site' => $request->getUri()->getHost(),
            'continue' => '/preferences',
        ]);
    }

    /** @param 'bar'|'password'|'two-factor'|'emails'|null $saved */
    private function page(ResponseInterface $response, Session $session, User $user, ?string $passwordError, int $status = 200, ?string $saved = null, ?string $twoFactorError = null): ResponseInterface
    {
        $schedule = ReportSchedule::fromCron($user->visitReport);
        $next = $schedule->nextDue($this->clock->now()->setTimezone($this->time->zone));

        return $this->view->render($response, 'cp/preferences.twig', [
            'report' => [
                'frequency' => $schedule->frequency,
                'hour' => $schedule->hour,
                'weekday' => $schedule->weekday,
                'date' => $schedule->date,
                'describe' => $schedule->describe(),
                'next' => $next?->format('l j F, H:i'),
            ],
            'weekdays' => ReportSchedule::WEEKDAYS,
            'last_date' => ReportSchedule::LAST_DATE,
            'alerts' => $user->alerts,
            'website_bar' => $user->websiteBar->value,
            'email' => $user->email,
            'saved' => $saved,
            'password_error' => $passwordError,
            'two_factor_error' => $twoFactorError,
            'codes_left' => $this->recoveryCodes->remaining($user->id),
            'min_length' => PasswordPolicy::MIN_LENGTH,
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
