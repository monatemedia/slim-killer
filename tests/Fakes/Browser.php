<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use SlimKiller\Auth\KnownBrowsers;
use SlimKiller\Http\Session\SessionMiddleware;

/**
 * Drives the control panel the way a browser does: keeps the session cookie, and
 * sends each form's CSRF token back from the page it came from.
 */
final class Browser
{
    private string $cookie = '';

    /** The browser's own cookie (sk_browser), kept across sign-outs like a real browser. */
    private string $browserCookie = '';

    private string $lastBody = '';

    /** @param App<\Psr\Container\ContainerInterface> $app */
    public function __construct(
        private readonly App $app,
        private readonly string $origin = 'https://client.test',
        private readonly string $ip = '203.0.113.5',
        private readonly string $userAgent = self::IPHONE,
    ) {}

    public const string IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6 Mobile/15E148 Safari/604.1';

    public const string WINDOWS_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    /** Another browser on the same website: no cookies of its own yet. */
    public function another(string $userAgent, string $ip = '198.51.100.7'): self
    {
        return new self($this->app, $this->origin, $ip, $userAgent);
    }

    public function get(string $path): ResponseInterface
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        return $this->send($this->request('GET', $path)->withQueryParams($query));
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files uploaded files, as PSR-7 has them: ['upload' => ['heroImage' => UploadedFile]]
     */
    public function post(string $path, array $fields, array $files = []): ResponseInterface
    {
        $fields += ['_csrf' => $this->csrf()];

        return $this->send($this->request('POST', $path)->withParsedBody($fields)->withUploadedFiles($files));
    }

    /**
     * A POST from another system rather than a form: a raw body and its own headers.
     *
     * @param array<string, string> $headers
     */
    public function postRaw(string $path, string $body, array $headers): ResponseInterface
    {
        $request = $this->request('POST', $path)->withBody((new StreamFactory())->createStream($body));
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->send($request);
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
            ->withHeader('User-Agent', $this->userAgent)
            ->withCookieParams(array_filter([SessionMiddleware::COOKIE => $this->cookie, KnownBrowsers::COOKIE => $this->browserCookie], static fn(string $value): bool => $value !== ''));
    }

    private function send(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->app->handle($request);
        $this->lastBody = (string) $response->getBody();

        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (preg_match('/^' . SessionMiddleware::COOKIE . '=([^;]*);.*Max-Age=(\d+)/', $header, $match) === 1) {
                $this->cookie = $match[2] === '0' ? '' : $match[1];
            }
            if (preg_match('/^' . KnownBrowsers::COOKIE . '=([^;]*);/', $header, $match) === 1) {
                $this->browserCookie = $match[1];
            }
        }

        return $response;
    }
}
