<?php

declare(strict_types=1);

namespace SlimKiller\Photos;

use SlimKiller\Support\Paths;

/**
 * Photos uploaded into a form but not saved yet, one folder per person, outside the web
 * root (storage/photos/staged/<user id>/). Each is already re-encoded; it goes to GitHub
 * with the page when the person presses Save, and is forgotten after a day if they never
 * do (Discard changes, a closed tab).
 */
final class StagedPhotos
{
    private const int KEEP_SECONDS = 86400;

    public function __construct(
        private readonly Paths $paths,
        private readonly PhotoLibrary $library,
    ) {}

    /**
     * Keeps a processed photo under a clean, unused file name made from the one it was
     * uploaded with ("Lions at dusk.JPG" → "lions-at-dusk.jpg"), and returns that name.
     */
    public function stage(int $user, ProcessedPhoto $photo, string $uploadedName): string
    {
        $this->forgetOld($user);
        $folder = $this->folder($user);
        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        $base = strtolower(pathinfo($uploadedName, PATHINFO_FILENAME));
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = substr($base, 0, 60) ?: 'photo';
        $file = $base . '.' . $photo->extension;
        for ($n = 2; $this->library->has($file) || is_file($folder . '/' . $file); $n++) {
            $file = $base . '-' . $n . '.' . $photo->extension;
        }

        file_put_contents($folder . '/' . $file, $photo->bytes);
        file_put_contents($folder . '/' . PhotoLibrary::previewName($file), $photo->preview);

        return $file;
    }

    /** True for a file name this person has staged (and nothing that could leave the folder). */
    public function has(int $user, string $file): bool
    {
        return self::isFileName($file) && is_file($this->folder($user) . '/' . $file);
    }

    public function bytes(int $user, string $file): ?string
    {
        return $this->has($user, $file) ? (string) file_get_contents($this->folder($user) . '/' . $file) : null;
    }

    public function previewPath(int $user, string $name): ?string
    {
        $path = $this->folder($user) . '/' . $name;

        return PhotoLibrary::isPreviewName($name) && is_file($path) ? $path : null;
    }

    /** @return array<string, string> file name => preview name */
    public function all(int $user): array
    {
        $photos = [];
        foreach (glob($this->folder($user) . '/*.{jpg,png}', GLOB_BRACE) ?: [] as $path) {
            $file = basename($path);
            if (self::isFileName($file) && ! PhotoLibrary::isPreviewName($file)) {
                $photos[$file] = PhotoLibrary::previewName($file);
            }
        }

        return $photos;
    }

    /** After a save: the photo is on GitHub, so its preview joins the library and the rest goes. */
    public function saved(int $user, string $file): void
    {
        $folder = $this->folder($user);
        $preview = $folder . '/' . PhotoLibrary::previewName($file);
        if (is_file($preview)) {
            $this->library->addUploaded($file, (string) file_get_contents($preview));
            @unlink($preview);
        }
        @unlink($folder . '/' . $file);
    }

    public static function isFileName(string $file): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*\.(jpg|png)$/', $file) === 1;
    }

    private function forgetOld(int $user): void
    {
        foreach (glob($this->folder($user) . '/*') ?: [] as $path) {
            if (is_file($path) && (int) filemtime($path) < time() - self::KEEP_SECONDS) {
                @unlink($path);
            }
        }
    }

    private function folder(int $user): string
    {
        return $this->paths->storage('photos/staged/' . $user);
    }
}
