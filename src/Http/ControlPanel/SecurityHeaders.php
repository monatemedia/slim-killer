<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Headers on every control panel response.
 *
 * - A strict Content Security Policy: styles, images and (later) scripts come from
 *   the panel itself. No inline styles or scripts, no CDN.
 * - No referrer: the setup page's address carries its secret token, and it must not
 *   travel to GitHub or anywhere else when someone follows a link.
 * - Never cached, never indexed, never framed.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    private const string POLICY = "default-src 'none'; style-src 'self'; img-src 'self'; "
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'none'";

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)
            ->withHeader('Content-Security-Policy', self::POLICY)
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'no-store');
    }
}
