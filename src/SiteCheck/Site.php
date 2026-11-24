<?php

declare(strict_types=1);

namespace SlimKiller\SiteCheck;

/**
 * A client site's repository, as `hammer site:check` reads it: content/ beside site/
 * (the Astro project), the schemas `astro sync` writes, and the last build in site/dist.
 * Read only: the check never changes a file.
 */
final readonly class Site
{
    /** Folders in content/ that hold files rather than a collection (Astro site rule 3). */
    public const array FILE_FOLDERS = ['images', 'graphics'];

    public string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root . '/' . ltrim($relative, '/');
    }

    public function has(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function read(string $relative): ?string
    {
        $file = $this->path($relative);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    /** @return array<mixed>|null */
    public function json(string $relative): ?array
    {
        $text = $this->read($relative);
        $data = $text === null ? null : json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    /**
     * The collections: every folder in content/ but images, graphics, and those starting
     * with "_" or ".".
     *
     * @return list<string>
     */
    public function collections(): array
    {
        $folders = [];
        foreach (glob($this->path('content') . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $name = basename($folder);
            if (! in_array($name, self::FILE_FOLDERS, true) && ! str_starts_with($name, '_') && ! str_starts_with($name, '.')) {
                $folders[] = $name;
            }
        }
        sort($folders);

        return $folders;
    }

    /**
     * Every file in a folder and below it, as paths relative to the site.
     *
     * @return list<string>
     */
    public function files(string $relative): array
    {
        $base = $this->path($relative);
        if (! is_dir($base)) {
            return [];
        }
        $files = [];
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if ($item instanceof \SplFileInfo && $item->isFile()) {
                $files[] = trim($relative, '/') . '/' . str_replace('\\', '/', substr($item->getPathname(), strlen($base) + 1));
            }
        }
        sort($files);

        return $files;
    }

    /** Where `astro sync` writes each collection's schema. */
    public function schemaFile(string $collection): string
    {
        return 'site/.astro/collections/' . $collection . '.schema.json';
    }
}
