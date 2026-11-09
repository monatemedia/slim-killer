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
}
