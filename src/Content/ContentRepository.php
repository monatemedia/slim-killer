<?php

declare(strict_types=1);

namespace SlimKiller\Content;

use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Reads the working copy: the collections that have both a schema and a content folder,
 * and the entries in them. Only reads; saving goes through GitHub.
 *
 * Files whose name starts with "_" are not content (templates, notes) and are skipped,
 * as Astro's glob loader skips them.
 */
final class ContentRepository
{
    private const string SLUG = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    public function __construct(
        private readonly ContentLocation $location,
    ) {}

    /** @return list<Collection> In alphabetical order of label. */
    public function collections(): array
    {
        $collections = [];
        foreach (glob($this->location->schemaDir . '/*.schema.json') ?: [] as $file) {
            $name = basename($file, '.schema.json');
            $collection = $this->collection($name);
            if ($collection !== null) {
                $collections[] = $collection;
            }
        }
        usort($collections, static fn(Collection $a, Collection $b): int => strcasecmp($a->label, $b->label));

        return $collections;
    }

    public function collection(string $name): ?Collection
    {
        if (preg_match(self::SLUG, $name) !== 1) {
            return null;
        }
        $schemaFile = $this->location->schemaDir . '/' . $name . '.schema.json';
        if (! is_file($schemaFile) || ! is_dir($this->location->contentDir . '/' . $name)) {
            return null;
        }
        $schema = json_decode((string) file_get_contents($schemaFile), true);
        if (! is_array($schema)) {
            return null;
        }
        $title = $schema['title'] ?? null;

        return new Collection($name, is_string($title) && $title !== '' ? $title : Labels::fromName($name), $schema);
    }

    /** @return list<Entry> In alphabetical order of title. */
    public function entries(Collection $collection): array
    {
        $entries = [];
        foreach (glob($this->location->contentDir . '/' . $collection->name . '/*.md') ?: [] as $file) {
            $slug = basename($file, '.md');
            if (str_starts_with($slug, '_')) {
                continue;
            }
            $entry = $this->entry($collection, $slug);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }
        usort($entries, static fn(Entry $a, Entry $b): int => strcasecmp($a->title, $b->title));

        return $entries;
    }

    public function entry(Collection $collection, string $slug): ?Entry
    {
        $raw = $this->raw($collection, $slug);
        if ($raw === null) {
            return null;
        }
        $file = ContentFile::parse($raw);
        try {
            $data = FrontMatter::parse($file->yaml)->data();
        } catch (ParseException) {
            $data = [];
        }
        $title = $data['title'] ?? null;

        return new Entry(
            $slug,
            is_string($title) && trim($title) !== '' ? $title : Labels::fromName($slug),
            $data,
            $file->body(),
            GitBlob::hash($raw),
        );
    }

    /** The entry's file exactly as stored, or null when there is no such entry. */
    public function raw(Collection $collection, string $slug): ?string
    {
        if (preg_match(self::SLUG, $slug) !== 1 || str_starts_with($slug, '_')) {
            return null;
        }
        $path = $this->location->contentDir . '/' . $collection->name . '/' . $slug . '.md';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * Writes a saved entry into the working copy, so the panel shows it at once. Written
     * to a temporary file first and renamed into place, so a reader never sees half a file.
     */
    public function write(Collection $collection, string $slug, string $bytes): void
    {
        if ($this->raw($collection, $slug) === null) {
            throw new \RuntimeException('There is no entry ' . $collection->name . '/' . $slug . ' to write.');
        }
        $path = $this->location->contentDir . '/' . $collection->name . '/' . $slug . '.md';
        $temporary = $path . '.saving';
        file_put_contents($temporary, $bytes);
        rename($temporary, $path);
    }
}
