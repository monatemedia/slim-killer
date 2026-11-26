<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AccountMail;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\BrowserName;
use SlimKiller\Auth\NewPassword;
use SlimKiller\Auth\PasswordPolicy;
use SlimKiller\Auth\PasswordResetLinks;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\Clock;

/**
 * The link from "Forgot your password?": choose a new password, then the code from
 * the authenticator app as at any sign-in. The account is signed out everywhere, and
 * its owner gets an email saying the password changed.
 *
 * Like the welcome link, the token is swapped for the session on the first visit and
 * the address bar is cleaned.
 */
final class ResetPasswordAction
{
    private const string RESET = 'reset_user_id';

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly PasswordResetLinks $links,
        private readonly NewPassword $newPassword,
        private readonly AccountMail $mail,
        private readonly AuditLog $audit,
        private readonly AfterResponse $afterResponse,
        private readonly Clock $clock,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        $token = Form::query($request, 'token');

        if ($token !== '') {
            $userId = $this->links->userFor($token);
            if ($userId === null) {
                return $this->expired($response);
            }

            $session->regenerate();
            $session->remove(Session::USER, SignIn::PENDING, SignIn::PENDING_SECRET);
            $session->set(self::RESET, $userId);

            return Form::redirect($response, '/reset-password');
        }

        $userId = $session->int(self::RESET);
        $user = $userId === null ? null : $this->users->byId($userId);
        if ($user === null || ! $user->hasTwoFactor() || ! $this->links->isOpenFor($user->id)) {
            return $this->expired($response);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($response, $session, $user, null);
        }

        $ip = Form::ip($request);
        $password = Form::field($request, 'password');
        $problem = $this->newPassword->problem($user, $password, Form::field($request, 'password_confirmation'), $ip);
        if ($problem !== null) {
            return $this->form($response, $session, $user, $problem, 422);
        }

        $this->newPassword->set($user, $password);
        $this->links->close($user->id);
        $this->users->signOutEverywhere($user->id);
        $this->audit->record('password_reset', $user->id, $ip);
        $browser = BrowserName::from($request->getHeaderLine('User-Agent'));
        $at = $this->clock->now();
        $this->afterResponse->run(fn(): bool => $this->mail->passwordChanged($user, $browser, $ip, $at));

        $session->regenerate();
        $session->remove(self::RESET);
        $session->set(SignIn::PENDING, $user->id);

        return Form::redirect($response, '/two-factor');
    }

    private function form(ResponseInterface $response, Session $session, User $user, ?string $error, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/reset-password.twig', [
            'email' => $user->email,
            'min_length' => PasswordPolicy::MIN_LENGTH,
            'error' => $error,
            'csrf' => $session->csrfToken(),
        ], $status);
    }

    private function expired(ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'cp/message.twig', [
            'title' => 'This link has expired',
            'message' => 'A link to choose a new password works for an hour, once. Ask for a new one.',
            'link' => ['path' => '/forgot-password', 'label' => 'Forgot your password?'],
        ], 403);
    }
}
