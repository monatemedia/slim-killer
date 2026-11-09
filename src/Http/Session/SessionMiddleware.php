<?php

declare(strict_types=1);

namespace SlimKiller\Http\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Support\Clock;

/**
 * Loads the session from its cookie before the request, and saves it after. The
 * cookie is HttpOnly, SameSite=Lax, limited to /cp, and Secure on HTTPS.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public const string COOKIE = 'sk_session';

    public function __construct(
        private readonly SessionStore $store,
        private readonly Clock $clock,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()[self::COOKIE] ?? null;
        $session = is_string($token) && $token !== '' ? $this->store->load($token) : null;
        $session ??= Session::start($this->clock->now()->getTimestamp());

        $response = $handler->handle($request->withAttribute(Session::class, $session));
        $secure = $request->getUri()->getScheme() === 'https';

        if ($session->isDestroyed()) {
            if (! $session->isNew()) {
                $this->store->delete($session->token());
            }

            return $response->withAddedHeader('Set-Cookie', $this->cookie('', -1, $secure));
        }

        if ($session->isNew() && $session->isEmpty()) {
            return $response;
        }

        $this->store->save($session);

        return $response->withAddedHeader('Set-Cookie', $this->cookie($session->token(), SessionStore::IDLE_SECONDS, $secure));
    }

    private function cookie(string $value, int $maxAge, bool $secure): string
    {
        return self::COOKIE . '=' . $value
            . '; Path=' . ControlPanel::BASE_PATH
            . '; Max-Age=' . max(0, $maxAge)
            . '; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : '');
    }
}
