<?php

declare(strict_types=1);

namespace SlimKiller\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body,
        public string $error = '',
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
}
