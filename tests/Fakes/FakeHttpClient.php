<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;

/**
 * Answers requests from a closure instead of the network, and remembers what was asked.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<string> */
    public array $requested = [];

    /** @param Closure(string): Response $answer */
    public function __construct(
        private readonly Closure $answer,
    ) {}

    public static function always(Response $response): self
    {
        return new self(static fn(string $url): Response => $response);
    }

    public function get(string $url, array $headers = [], int $timeoutSeconds = 20): Response
    {
        $this->requested[] = $url;

        return ($this->answer)($url);
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): Response
    {
        $this->requested[] = $method . ' ' . $url;

        return ($this->answer)($url);
    }

    /** Writes the answer's body to the file, as the real client streams it. */
    public function download(string $url, array $headers, string $path, int $timeoutSeconds = 300): Response
    {
        $this->requested[] = 'DOWNLOAD ' . $url;
        $response = ($this->answer)($url);
        file_put_contents($path, $response->body);

        return new Response($response->status, '', $response->error);
    }
}
