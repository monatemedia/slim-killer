<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\WebProbe;

/**
 * The website to probe: its public address, and the folder on disk that address serves.
 */
final readonly class WebTarget
{
    public function __construct(
        public string $url,
        public string $webRoot,
    ) {}

    public static function from(string $url, string $webRoot): self
    {
        if (str_starts_with($webRoot, '~')) {
            $home = getenv('HOME') ?: '';
            $webRoot = $home . substr($webRoot, 1);
        }

        return new self(rtrim($url, '/'), rtrim($webRoot, '/\\'));
    }
}
