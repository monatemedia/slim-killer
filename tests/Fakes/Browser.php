<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use SlimKiller\Http\Session\SessionMiddleware;

/**
 * Drives the control panel the way a browser does: keeps the session cookie, and
 * sends each form's CSRF token back from the page it came from.
 */
final class Browser
{
    private string $cookie = '';

    private string $lastBody = '';

    /** @param App<\Psr\Container\ContainerInterface> $app */
    public function __construct(
        private readonly App $app,
        private readonly string $origin = 'https://client.test',
        private readonly string $ip = '203.0.113.5',
    ) {}

    public function get(string $path): ResponseInterface
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        return $this->send($this->request('GET', $path)->withQueryParams($query));
    }

    /** @param array<string, mixed> $fields */
    public function post(string $path, array $fields): ResponseInterface
    {
        $fields += ['_csrf' => $this->csrf()];

        return $this->send($this->request('POST', $path)->withParsedBody($fields));
    }

    public function body(): string
    {
        return $this->lastBody;
    }

    public function csrf(): string
    {
        return preg_match('/name="_csrf" value="([^"]+)"/', $this->lastBody, $match) === 1 ? $match[1] : '';
    }

    public function hasSession(): bool
    {
        return $this->cookie !== '';
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest($method, $this->origin . $path, ['REMOTE_ADDR' => $this->ip])
            ->withCookieParams($this->cookie === '' ? [] : [SessionMiddleware::COOKIE => $this->cookie]);
    }

    private function send(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->app->handle($request);
        $this->lastBody = (string) $response->getBody();

        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (preg_match('/^' . SessionMiddleware::COOKIE . '=([^;]*);.*Max-Age=(\d+)/', $header, $match) === 1) {
                $this->cookie = $match[2] === '0' ? '' : $match[1];
            }
        }

        return $response;
    }
}
