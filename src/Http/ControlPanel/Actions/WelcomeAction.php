<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\Invites;
use SlimKiller\Auth\PasswordHasher;
use SlimKiller\Auth\PasswordPolicy;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * The welcome link from `hammer install`, `user:create` or `user:reset`: choose a
 * password, then set up two-factor.
 *
 * The token is swapped for the session on the first visit and the address bar is
 * cleaned, so the link does not linger in the browser's history.
 */
final class WelcomeAction
{
    private const string WELCOME = 'welcome_user_id';

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly Invites $invites,
        private readonly PasswordHasher $hasher,
        private readonly AuditLog $audit,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        $token = Form::query($request, 'token');

        if ($token !== '') {
            $userId = $this->invites->userFor($token);
            if ($userId === null) {
                return $this->expired($response);
            }

            $session->regenerate();
            $session->remove(Session::USER, SignIn::PENDING, SignIn::PENDING_SECRET);
            $session->set(self::WELCOME, $userId);

            return Form::redirect($response, '/welcome');
        }

        $userId = $session->int(self::WELCOME);
        $user = $userId === null ? null : $this->users->byId($userId);
        if ($user === null || ! $this->invites->isOpenFor($user->id)) {
            return $this->expired($response);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($response, $session, $user, null);
        }

        $password = Form::field($request, 'password');
        $problem = PasswordPolicy::problem($password, Form::field($request, 'password_confirmation'));
        if ($problem !== null) {
            return $this->form($response, $session, $user, $problem, 422);
        }

        $this->users->setPassword($user->id, $this->hasher->hash($password));
        $this->invites->close($user->id);
        $this->audit->record('password_set', $user->id, Form::ip($request));

        $session->regenerate();
        $session->remove(self::WELCOME);
        $session->set(SignIn::PENDING, $user->id);

        return Form::redirect($response, '/two-factor/setup');
    }

    private function form(ResponseInterface $response, Session $session, User $user, ?string $error, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/welcome.twig', [
            'name' => $user->name,
            'email' => $user->email,
            'min_length' => PasswordPolicy::MIN_LENGTH,
            'error' => $error,
            'csrf' => $session->csrfToken(),
        ], $status);
    }

    private function expired(ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'cp/message.twig', [
            'title' => 'This welcome link has expired',
            'message' => 'Welcome links work for 72 hours, once. Ask whoever set up your account for a new one.',
            'link' => null,
        ], 403);
    }
}
