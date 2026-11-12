<?php

declare(strict_types=1);

namespace SlimKiller\Http;

/**
 * The smallest HTTP client Slim Killer needs. Everything that talks to the network goes
 * through this interface, so tests use a fake and never touch the internet.
 */
interface HttpClient
{
    /** @param array<string, string> $headers */
    public function get(string $url, array $headers = [], int $timeoutSeconds = 20): Response;

    /**
     * Any method with an optional body (POST and PATCH with JSON, for the GitHub API).
     *
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): Response;

    /**
     * A GET whose body is streamed into a file rather than held in memory (a site build
     * is tens of megabytes). Follows redirects. The response's body is empty.
     *
     * @param array<string, string> $headers
     */
    public function download(string $url, array $headers, string $path, int $timeoutSeconds = 300): Response;
}
