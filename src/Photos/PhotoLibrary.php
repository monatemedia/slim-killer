<?php

declare(strict_types=1);

namespace SlimKiller\Photos;

use SlimKiller\Content\ContentLocation;
use SlimKiller\Support\Paths;

/**
 * The site's photos (content/images/), as the panel shows them: each file name and a
 * small preview.
 *
 * Previews come from the site's build (_slim-killer/previews.json and previews/, kept
 * beside the working copy by every fetch; for local development, read from the site's
 * last build). A photo uploaded through the panel has its own preview in
 * storage/photos/previews/ until the next build makes one, so it shows straight away.
 * Previews are named after a hash of the file name: "<16 hex>.webp" or ".jpg".
 */
final class PhotoLibrary
{
    /** @var array<string, string>|null file name => preview file name */
    private ?array $built = null;

    public function __construct(
        private readonly ContentLocation $location,
        private readonly Paths $paths,
    ) {}

    /** The preview's name for a photo uploaded through the panel. */
    public static function previewName(string $file): string
    {
        return substr(sha1($file), 0, 16) . '.jpg';
    }

    public static function isPreviewName(string $name): bool
    {
        return preg_match('/^[0-9a-f]{16}\.(webp|jpg)$/', $name) === 1;
    }

    /**
     * Every photo with a preview, by file name.
     *
     * @return array<string, string> file name => preview name
     */
    public function all(): array
    {
        $photos = $this->built();
        foreach ($this->uploaded() as $file) {
            $photos[$file] ??= self::previewName($file);
        }
        ksort($photos, SORT_NATURAL | SORT_FLAG_CASE);

        return $photos;
    }

    public function has(string $file): bool
    {
        return isset($this->all()[$file]);
    }

    /** Where a preview is on disk, or null for a name the library does not have. */
    public function previewPath(string $name): ?string
    {
        if (! self::isPreviewName($name)) {
            return null;
        }
        foreach ([$this->builtFolder() . '/previews/' . $name, $this->uploadedFolder() . '/' . $name] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Keeps the preview of a photo just committed, until a build brings its own. */
    public function addUploaded(string $file, string $preview): void
    {
        $folder = $this->uploadedFolder();
        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }
        @file_put_contents($folder . '/' . self::previewName($file), $preview);
        $list = $this->uploaded();
        $list[] = $file;
        @file_put_contents($folder . '/uploaded.json', json_encode(array_values(array_unique($list)), JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @return array<string, string> */
    private function built(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }
        $data = self::json($this->builtFolder() . '/previews.json');
        $this->built = [];
        foreach (is_array($data) ? $data : [] as $file => $photo) {
            $preview = is_array($photo) ? ($photo['preview'] ?? null) : null;
            if (is_string($file) && is_string($preview) && self::isPreviewName($preview)) {
                $this->built[$file] = $preview;
            }
        }

        return $this->built;
    }

    /** @return list<string> */
    private function uploaded(): array
    {
        $data = self::json($this->uploadedFolder() . '/uploaded.json');

        return array_values(array_filter(is_array($data) ? $data : [], 'is_string'));
    }

    private static function json(string $file): mixed
    {
        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    /** Beside the working copy on a host; the site's last build when developing. */
    private function builtFolder(): string
    {
        $site = dirname($this->location->contentDir);
        foreach ([$site, $site . '/site/dist/_slim-killer'] as $folder) {
            if (is_file($folder . '/previews.json')) {
                return $folder;
            }
        }

        return $site;
    }

    private function uploadedFolder(): string
    {
        return $this->paths->storage('photos/previews');
    }
}
