<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AccountMail;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\PasswordResetLinks;
use SlimKiller\Auth\SignInThrottle;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;

/**
 * "Forgot your password?": emails a link to choose a new one, valid for an hour.
 *
 * The answer is the same whether or not the address has an account, so the page can't
 * be used to find out who has one. Only an account that has finished setting up gets a
 * link (someone still setting up needs their welcome link). The email goes after the
 * page has gone, so the answer takes as long either way.
 */
final class ForgotPasswordAction
{
    public const int MAX_PER_EMAIL = 3;

    public const int MAX_PER_IP = 10;

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly PasswordResetLinks $links,
        private readonly AccountMail $mail,
        private readonly SignInThrottle $throttle,
        private readonly AuditLog $audit,
        private readonly AfterResponse $afterResponse,
        private readonly AppConfig $config,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        if ($session->userId() !== null || ! $this->users->any()) {
            return Form::redirect($response, '/');
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($response, $session, '', null);
        }

        $email = strtolower(trim(Form::field($request, 'email')));
        if ($email === '') {
            return $this->form($response, $session, '', 'Type the email address you sign in with.', 422);
        }

        $ip = Form::ip($request);
        $keys = ['forgot:' . $email => self::MAX_PER_EMAIL, 'forgot-ip:' . $ip => self::MAX_PER_IP];
        $wait = $this->throttle->waitFor($keys);
        if ($wait > 0) {
            return $this->form($response, $session, $email, 'Too many requests. Try again in ' . Form::minutes($wait) . '.', 429);
        }
        // Every request counts, not only failures: each one can send an email.
        $this->throttle->fail(...array_keys($keys));

        $user = $this->users->byEmail($email);
        if ($user !== null && $user->hasPassword() && $user->hasTwoFactor()) {
            $this->audit->record('password_reset_requested', $user->id, $ip);
            $link = $this->config->link(PasswordResetLinks::pathFor($this->links->issue($user->id)));
            $this->afterResponse->run(fn(): bool => $this->mail->passwordReset($user, $link));
        }

        return $this->view->render($response, 'cp/message.twig', [
            'title' => 'Check your email',
            'message' => 'If ' . $email . ' has an account here, an email is on its way with a link to choose a new password. The link works for an hour, once. Nothing there? Check your spam folder, or ask whoever set up your account.',
            'link' => ['path' => '/sign-in', 'label' => 'Back to signing in'],
        ]);
    }

    private function form(ResponseInterface $response, Session $session, string $email, ?string $error, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/forgot-password.twig', [
            'email' => $email,
            'error' => $error,
            'can_send' => $this->mail->canSend(),
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
