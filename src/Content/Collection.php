<?php

declare(strict_types=1);

namespace SlimKiller\Content;

/**
 * A group of content files of one kind ("articles"), with the schema Astro generated
 * for it. See the wiki's Glossary: collection.
 */
final readonly class Collection
{
    /** @param array<mixed> $schema The collection's JSON Schema, as `astro sync` writes it. */
    public function __construct(
        public string $name,
        public string $label,
        public array $schema,
    ) {}
}
