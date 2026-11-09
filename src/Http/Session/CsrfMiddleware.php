<?php

declare(strict_types=1);

namespace SlimKiller\Http\Session;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SlimKiller\Http\ControlPanel\View;

/**
 * Every form carries a token from the session, and every POST must send it back. A
 * form on another website cannot know it, so it cannot act for a signed-in user.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const string FIELD = '_csrf';

    public function __construct(
        private readonly View $view,
        private readonly ResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $sent = is_array($body) ? ($body[self::FIELD] ?? null) : null;
        $expected = Session::of($request)->string('_csrf');

        if (! is_string($sent) || $expected === null || ! hash_equals($expected, $sent)) {
            return $this->view->render($this->responses->createResponse(), 'cp/message.twig', [
                'title' => 'This form has expired',
                'message' => 'The page was open for too long, or came from somewhere else. Go back, reload the page and try again.',
                'link' => null,
            ], 403);
        }

        return $handler->handle($request);
    }
}
