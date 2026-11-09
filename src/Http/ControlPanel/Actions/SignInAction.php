<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\SignInThrottle;
use SlimKiller\Auth\PasswordHasher;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * Step one of signing in: email address and password. Step two is always a code
 * from the authenticator app (TwoFactorAction), or setting one up (TwoFactorSetupAction).
 */
final class SignInAction
{
    public const int MAX_PER_EMAIL = 5;

    public const int MAX_PER_IP = 20;

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly SignInThrottle $throttle,
        private readonly AuditLog $audit,
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
        $password = Form::field($request, 'password');
        $ip = Form::ip($request);
        $keys = ['email:' . $email => self::MAX_PER_EMAIL, 'ip:' . $ip => self::MAX_PER_IP];

        if ($email === '' || $password === '') {
            return $this->form($response, $session, $email, 'Type your email address and your password.', 422);
        }

        $wait = $this->throttle->waitFor($keys);
        if ($wait > 0) {
            return $this->form($response, $session, $email, 'Too many attempts. Try again in ' . Form::minutes($wait) . '.', 429);
        }

        $user = $this->users->byEmail($email);
        $hash = $user?->passwordHash;
        if ($hash === null) {
            $this->hasher->verifyNothing($password);
        }

        if ($user === null || $hash === null || ! $this->hasher->verify($password, $hash)) {
            $this->throttle->fail(...array_keys($keys));
            $this->audit->record('sign_in_failed', $user?->id, $ip);

            return $this->form($response, $session, $email, 'That email address and password do not match.', 422);
        }

        if ($this->hasher->needsRehash($hash)) {
            $this->users->setPassword($user->id, $this->hasher->hash($password));
        }

        $this->throttle->clear('email:' . $email);
        $session->regenerate();
        $session->set(SignIn::PENDING, $user->id);

        return Form::redirect($response, $user->hasTwoFactor() ? '/two-factor' : '/two-factor/setup');
    }

    private function form(ResponseInterface $response, Session $session, string $email, ?string $error, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/sign-in.twig', [
            'email' => $email,
            'error' => $error,
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
