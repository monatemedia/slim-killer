<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\LoginThrottle;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\User;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\SignIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;

/**
 * Step two of signing in: the six-digit code from the authenticator app, or one of
 * the recovery codes. A code can only be used once.
 */
final class TwoFactorAction
{
    public const int MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly LoginThrottle $throttle,
        private readonly AuditLog $audit,
        private readonly SignIn $signIn,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        $pending = $session->int(SignIn::PENDING);
        $user = $pending === null ? null : $this->users->byId($pending);

        if ($user === null || $user->totpSecret === null) {
            return Form::redirect($response, '/login');
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($response, $session, null);
        }

        $key = 'code:' . $user->id;
        $wait = $this->throttle->waitFor([$key => self::MAX_ATTEMPTS]);
        if ($wait > 0) {
            return $this->form($response, $session, 'Too many attempts. Try again in ' . Form::minutes($wait) . '.', 429);
        }

        $ip = Form::ip($request);
        if (! $this->accepts($user, $user->totpSecret, Form::field($request, 'code'), $ip)) {
            $this->throttle->fail($key);
            $this->audit->record('two_factor_failed', $user->id, $ip);

            return $this->form($response, $session, 'That code is not right. The codes change every 30 seconds.', 422);
        }

        $this->throttle->clear($key);
        $this->signIn->complete($session, $user, $ip);

        return Form::redirect($response, '/');
    }

    private function accepts(User $user, string $secret, string $code, string $ip): bool
    {
        if (RecoveryCodes::looksLikeOne($code)) {
            $used = $this->recoveryCodes->use($user->id, $code);
            if ($used) {
                $this->audit->record('recovery_code_used', $user->id, $ip);
            }

            return $used;
        }

        $counter = $this->totp->match($secret, $code, $user->totpLastCounter);
        if ($counter === null) {
            return false;
        }

        $this->users->recordTotpCounter($user->id, $counter);

        return true;
    }

    private function form(ResponseInterface $response, Session $session, ?string $error, int $status = 200): ResponseInterface
    {
        return $this->view->render($response, 'cp/two-factor.twig', [
            'error' => $error,
            'csrf' => $session->csrfToken(),
        ], $status);
    }
}
