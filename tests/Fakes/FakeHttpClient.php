<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;

/**
 * Answers requests from a closure instead of the network, and remembers what was asked.
 * The closure gets the address, and the method and body for those that need them.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<string> */
    public array $requested = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $sent = [];

    /** @param Closure(string, string, ?string): Response $answer */
    public function __construct(
        private readonly Closure $answer,
    ) {}

    public static function always(Response $response): self
    {
        return new self(static fn(): Response => $response);
    }

    public function get(string $url, array $headers = [], int $timeoutSeconds = 20): Response
    {
        $this->requested[] = $url;
        $this->sent[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers, 'body' => null];

        return ($this->answer)($url, 'GET', null);
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): Response
    {
        $this->requested[] = $method . ' ' . $url;
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return ($this->answer)($url, $method, $body);
    }

    /** Writes the answer's body to the file, as the real client streams it. */
    public function download(string $url, array $headers, string $path, int $timeoutSeconds = 300): Response
    {
        $this->requested[] = 'DOWNLOAD ' . $url;
        $this->sent[] = ['method' => 'DOWNLOAD', 'url' => $url, 'headers' => $headers, 'body' => null];
        $response = ($this->answer)($url, 'GET', null);
        file_put_contents($path, $response->body);

        return new Response($response->status, '', $response->error);
    }
}
