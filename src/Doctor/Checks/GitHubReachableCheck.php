<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Http\HttpClient;

/**
 * Saves are committed through api.github.com, and builds are downloaded from
 * codeload.github.com. Some budget hosts block or filter outgoing connections.
 */
final class GitHubReachableCheck implements HostCheck
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function check(): array
    {
        return [
            // The API answers /zen with 200 and a short proverb to anyone.
            $this->reach('https://api.github.com/zen', 'api.github.com', expectOk: true),
            // codeload answers "/" with 404; any answer at all proves the connection.
            $this->reach('https://codeload.github.com/', 'codeload.github.com', expectOk: false),
        ];
    }

    private function reach(string $url, string $host, bool $expectOk): CheckResult
    {
        $response = $this->http->get($url, [], 15);

        if ($response->status === 0) {
            return CheckResult::fail(
                "Cannot reach {$host}",
                $response->error !== '' ? $response->error : 'no response',
                "Ask your host to allow outgoing HTTPS connections to {$host}.",
            );
        }

        if ($expectOk && ! $response->ok()) {
            return CheckResult::warn(
                "{$host} answered HTTP {$response->status}",
                'the connection works, but something between this server and GitHub may be filtering it',
                "If saving fails later, ask your host whether outgoing HTTPS to {$host} is filtered.",
            );
        }

        return CheckResult::pass("Can reach {$host}");
    }
}
