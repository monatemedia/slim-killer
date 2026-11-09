<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\QrCode;
use SlimKiller\Http\ControlPanel\SignIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AppConfig;

/**
 * Two-factor is required for everyone, so the first sign-in sets it up: scan the QR
 * code, type the code the app shows to prove it works, then keep the recovery codes.
 */
final class TwoFactorSetupAction
{
    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly AuditLog $audit,
        private readonly SignIn $signIn,
        private readonly AppConfig $config,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $session = Session::of($request);
        $pending = $session->int(SignIn::PENDING);
        $user = $pending === null ? null : $this->users->byId($pending);

        if ($user === null || ! $user->hasPassword() || $user->hasTwoFactor()) {
            return Form::redirect($response, '/sign-in');
        }

        $secret = $session->string(SignIn::PENDING_SECRET);
        if ($secret === null) {
            $secret = Totp::newSecret();
            $session->set(SignIn::PENDING_SECRET, $secret);
        }

        $page = [
            // The name the authenticator app shows: the site's own name, so a client with
            // several sites can tell them apart.
            'qr' => QrCode::svg(Totp::uri($secret, $user->email, $this->config->name . ' (' . $request->getUri()->getHost() . ')')),
            'secret' => implode(' ', str_split($secret, 4)),
            'error' => null,
            'csrf' => $session->csrfToken(),
        ];

        if ($request->getMethod() !== 'POST') {
            return $this->view->render($response, 'cp/two-factor-setup.twig', $page);
        }

        $counter = $this->totp->match($secret, Form::field($request, 'code'));
        if ($counter === null) {
            $page['error'] = 'That code is not right. Check the time on your phone is set automatically, and try the newest code.';

            return $this->view->render($response, 'cp/two-factor-setup.twig', $page, 422);
        }

        $this->users->enableTwoFactor($user->id, $secret, $counter);
        $codes = $this->recoveryCodes->replace($user->id);
        $ip = Form::ip($request);
        $this->audit->record('two_factor_enabled', $user->id, $ip);
        $this->signIn->complete($session, $user, $ip);

        return $this->view->render($response, 'cp/recovery-codes.twig', [
            'codes' => $codes,
            'account' => $user->email,
            'site' => $request->getUri()->getHost(),
        ]);
    }
}
