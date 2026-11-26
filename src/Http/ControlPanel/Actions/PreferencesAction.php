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
use SlimKiller\Auth\SignInThrottle;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WebsiteBar;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\Clock;

/**
 * /cp/preferences: each person's own choices. Whether they see the website bar on the
 * website, and their password.
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
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = SignedIn::user($request);
        $session = Session::of($request);

        if ($request->getMethod() === 'POST' && Form::field($request, 'form') === 'password') {
            return $this->changePassword($request, $response, $session, $user);
        }

        if ($request->getMethod() === 'POST') {
            $bar = WebsiteBar::tryFrom(Form::field($request, 'website_bar')) ?? $user->websiteBar;
            $this->users->setWebsiteBar($user->id, $bar);

            return Form::redirect($response, '/preferences?saved=1');
        }

        return $this->page($response, $session, $user, null, 200, match (Form::query($request, 'saved')) {
            '1' => 'bar',
            'password' => 'password',
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

    /** @param 'bar'|'password'|null $saved */
    private function page(ResponseInterface $response, Session $session, User $user, ?string $passwordError, int $status = 200, ?string $saved = null): ResponseInterface
    {
        return $this->view->render($response, 'cp/preferences.twig', [
            'website_bar' => $user->websiteBar->value,
            'email' => $user->email,
            'saved' => $saved,
            'password_error' => $passwordError,
            'min_length' => PasswordPolicy::MIN_LENGTH,
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
