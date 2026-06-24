<?php

declare(strict_types=1);

namespace Bond\Http\Problem;

/**
 * ProblemDetails — an RFC 7807 "Problem Details" response (status + body).
 *
 * RFC 7807 defines a small standard JSON shape for HTTP errors — `type`, `title`,
 * `status`, `detail`, `instance` — plus arbitrary safe "extension members" (here:
 * `context` for domain failures, `reference` for server errors). The media type is
 * `application/problem+json`, which tells clients "this body is a structured error".
 */
final readonly class ProblemDetails
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public int $status,
        public array $body,
    ) {}

    public function contentType(): string
    {
        return 'application/problem+json';
    }

    public function toJson(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
