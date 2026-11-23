<?php

declare(strict_types=1);

namespace SlimKiller\Http;

final readonly class Response
{
    /** @param array<string, string> $headers by lower-case name, like "location" */
    public function __construct(
        public int $status,
        public string $body,
        public string $error = '',
        public array $headers = [],
    ) {}

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @return array<mixed>|null */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }

    /** A header's value, or an empty string when the response has none. */
    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }
}
