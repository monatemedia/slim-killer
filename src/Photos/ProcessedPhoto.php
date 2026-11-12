<?php

declare(strict_types=1);

namespace SlimKiller\Photos;

/** A photo as the panel keeps it: re-encoded bytes, its extension, and a small preview (JPEG). */
final readonly class ProcessedPhoto
{
    public function __construct(
        public string $bytes,
        public string $extension,
        public string $preview,
        public int $width,
        public int $height,
    ) {}
}
