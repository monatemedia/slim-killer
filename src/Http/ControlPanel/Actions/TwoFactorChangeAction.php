<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AccountMail;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\BrowserName;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\Form;
use SlimKiller\Http\ControlPanel\QrCode;
use SlimKiller\Http\ControlPanel\SignedIn;
use SlimKiller\Http\ControlPanel\View;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Clock;

/**
 * /cp/two-factor/change: setting up two-factor again, for a new phone or when the old
 * setup key may have been seen. Reached from Preferences after the password and a code
 * from the app in use now (PreferencesAction), within ten minutes.
 *
 * The new app is scanned and proved with a code; only then does the old one stop
 * working. New recovery codes replace the old, the person's other devices are signed
 * out, and they get an email saying so.
 */
final class TwoFactorChangeAction
{
    public const string SECRET = 'new_totp_secret';

    public const string CONFIRMED_AT = 'two_factor_confirmed_at';

    public const int WINDOW_SECONDS = 600;

    public function __construct(
        private readonly View $view,
        private readonly UserRepository $users,
        private readonly Totp $totp,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly AccountMail $mail,
        private readonly AuditLog $audit,
        private readonly AfterResponse $afterResponse,
        private readonly Clock $clock,
        private readonly AppConfig $config,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = SignedIn::user($request);
        $session = Session::of($request);
        $secret = $session->string(self::SECRET);
        $confirmedAt = $session->int(self::CONFIRMED_AT);

        if ($secret === null || $confirmedAt === null || $this->clock->now()->getTimestamp() - $confirmedAt > self::WINDOW_SECONDS) {
            $session->remove(self::SECRET, self::CONFIRMED_AT);

            return Form::redirect($response, '/preferences#two-factor');
        }

        $page = [
            'qr' => QrCode::svg(Totp::uri($secret, $user->email, $this->config->name . ' (' . $request->getUri()->getHost() . ')')),
            'secret' => implode(' ', str_split($secret, 4)),
            'error' => null,
            'csrf' => $session->csrfToken(),
            'again' => true,
        ];

        if ($request->getMethod() !== 'POST') {
            return $this->view->render($response, 'cp/two-factor-setup.twig', $page);
        }

        $counter = $this->totp->match($secret, Form::field($request, 'code'));
        if ($counter === null) {
            $page['error'] = 'That code is not right. Type the code the new app shows for ' . $this->config->name . ', not the old one.';

            return $this->view->render($response, 'cp/two-factor-setup.twig', $page, 422);
        }

        $ip = Form::ip($request);
        $this->users->enableTwoFactor($user->id, $secret, $counter);
        $codes = $this->recoveryCodes->replace($user->id);
        $this->users->signOutEverywhere($user->id, $session->token());
        $session->regenerate();
        $session->remove(self::SECRET, self::CONFIRMED_AT);
        $this->audit->record('two_factor_changed', $user->id, $ip);
        $browser = BrowserName::from($request->getHeaderLine('User-Agent'));
        $at = $this->clock->now();
        $this->afterResponse->run(fn(): bool => $this->mail->twoFactorChanged($user, 'app', $browser, $ip, $at));

        return $this->view->render($response, 'cp/recovery-codes.twig', [
            'codes' => $codes,
            'account' => $user->email,
            'site' => $request->getUri()->getHost(),
            'continue' => '/preferences?saved=two-factor',
        ]);
    }
}
