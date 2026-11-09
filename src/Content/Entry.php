<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * One content file: its front matter values, its Markdown body, and its version (the Git
 * blob hash of the file as it was read). See the wiki's Glossary: entry.
 */
final readonly class Entry
{
    /** @param array<string, mixed> $data The front matter, parsed. */
    public function __construct(
        public string $slug,
        public string $title,
        public array $data,
        public string $body,
        public string $version,
    ) {}
}
