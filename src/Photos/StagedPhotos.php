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

    /** Names that say nothing about the photo: what phones and cameras call a file. */
    private const string MEANINGLESS = '/^(?:[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}|whatsapp-image.*|(?:img|dsc|dscn|dscf|pxl|mvimg|image|photo|pic|screenshot|wp)?(?:-?[0-9]+)*)$/';

    /**
     * Keeps a processed photo under a clean, unused file name made from the one it was
     * uploaded with ("Lions at dusk.JPG" → "lions-at-dusk.jpg"), and returns that name.
     * A name that says nothing ("IMG_2041", or the random one an iPhone sends) is
     * replaced by $fallback: the photo's description, or the page's title ("Discover
     * Botswana" → "discover-botswana.jpg").
     */
    public function stage(int $user, ProcessedPhoto $photo, string $uploadedName, string $fallback = 'photo'): string
    {
        $this->forgetOld($user);
        $folder = $this->folder($user);
        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        $base = self::slug(pathinfo($uploadedName, PATHINFO_FILENAME));
        if (preg_match(self::MEANINGLESS, $base) === 1) {
            $base = self::slug($fallback);
        }
        if (strlen($base) > 60) {
            // Cut at a word, not in the middle of one.
            $base = substr($base, 0, 60);
            $base = str_contains($base, '-') ? substr($base, 0, (int) strrpos($base, '-')) : $base;
        }
        $base = $base !== '' ? $base : 'photo';
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

    private static function slug(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
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
