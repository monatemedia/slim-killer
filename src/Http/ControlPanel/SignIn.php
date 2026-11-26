<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\BrowserName;
use SlimKiller\Auth\KnownBrowsers;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\SignInNotice;
use SlimKiller\Auth\User;
use SlimKiller\Http\Session\Session;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\Clock;

/**
 * The last step of every sign-in, once the password and the second factor have both
 * been checked: a fresh session token, a line in the audit log, and the browser noted.
 * A sign-in from a browser this person hasn't used, or with a recovery code, is emailed
 * to them, after the page has gone, so signing in isn't kept waiting.
 */
final class SignIn
{
    public const string PENDING = 'pending_user_id';

    public const string PENDING_SECRET = 'pending_totp_secret';

    public function __construct(
        private readonly AuditLog $audit,
        private readonly KnownBrowsers $browsers,
        private readonly RecoveryCodes $recoveryCodes,
        private readonly SignInNotice $notice,
        private readonly AfterResponse $afterResponse,
        private readonly Clock $clock,
    ) {}

    public function complete(Session $session, User $user, ServerRequestInterface $request, ResponseInterface $response, bool $recoveryCode = false): ResponseInterface
    {
        $ip = Form::ip($request);
        $session->regenerate();
        $session->remove(self::PENDING, self::PENDING_SECRET);
        $session->set(Session::USER, $user->id);
        $this->audit->record('sign_in', $user->id, $ip);

        $token = KnownBrowsers::token($request->getCookieParams()[KnownBrowsers::COOKIE] ?? null);
        $browser = BrowserName::from($request->getHeaderLine('User-Agent'));
        $seen = $this->browsers->signedIn($user->id, $token, $browser);
        if ($seen === 'new') {
            $this->audit->record('new_browser', $user->id, $ip);
        }

        if ($seen === 'new' || $recoveryCode) {
            $codesLeft = $recoveryCode ? $this->recoveryCodes->remaining($user->id) : null;
            $at = $this->clock->now();
            $this->afterResponse->run(fn(): bool => $this->notice->send($user, $browser, $ip, $at, $seen === 'new', $codesLeft));
        }

        return $response->withAddedHeader('Set-Cookie', self::cookie($token, $request->getUri()->getScheme() === 'https'));
    }

    /** Lasts 400 days, the longest browsers keep a cookie, and is renewed at each sign-in. */
    private static function cookie(string $token, bool $secure): string
    {
        return KnownBrowsers::COOKIE . '=' . $token
            . '; Path=' . ControlPanel::BASE_PATH
            . '; Max-Age=' . KnownBrowsers::KEEP_SECONDS
            . '; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : '');
    }
}
