<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;

/**
 * An address in the panel that doesn't exist, like /cp/login or /cp/admin: a page
 * request goes to the dashboard, which sends anyone signed out to the sign-in page.
 * A missing file (a name with a dot, like assets/old.css), a form sent to a wrong
 * address, and a real page that isn't there (an entry that was deleted) still get a 404.
 */
final readonly class NotFound
{
    /** @var callable(ServerRequestInterface, HttpNotFoundException, bool, bool, bool): ResponseInterface */
    private mixed $default;

    /** @param callable $default Slim's own error handler */
    public function __construct(
        private ResponseFactoryInterface $responses,
        callable $default,
    ) {
        $this->default = $default;
    }

    public function __invoke(ServerRequestInterface $request, HttpNotFoundException $exception, bool $details, bool $log, bool $logDetails): ResponseInterface
    {
        $noRoute = $exception->getRequest()->getAttribute(RouteContext::ROUTE) === null;
        if (! $noRoute || $request->getMethod() !== 'GET' || str_contains(basename($request->getUri()->getPath()), '.')) {
            return ($this->default)($request, $exception, $details, $log, $logDetails);
        }

        return $this->responses->createResponse(302)->withHeader('Location', ControlPanel::BASE_PATH . '/');
    }
}
