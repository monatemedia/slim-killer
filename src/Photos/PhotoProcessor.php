<?php

declare(strict_types=1);

namespace SlimKiller\Photos;

use finfo;
use GdImage;

/**
 * Turns an uploaded file into a photo the site can use, or refuses it.
 *
 * Nothing the client uploaded is kept: the file must really be a JPEG, PNG or WebP
 * (its first bytes and its content agree), it is decoded by GD, turned the right way up
 * (a phone's EXIF orientation), made at most 2400 px on the long edge, and written out
 * again. That drops EXIF (GPS location included) and anything hidden in the file, such as
 * PHP code. Photos become JPEG at quality 85; a PNG with transparency stays PNG.
 */
final class PhotoProcessor
{
    public const int MAX_BYTES = 20 * 1024 * 1024;

    public const int LONG_EDGE = 2400;

    public const int PREVIEW_EDGE = 400;

    /** About 40 megapixels: bigger than any phone, small enough to decode safely. */
    public const int MAX_PIXELS = 40_000_000;

    private const array TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public function process(string $file): ProcessedPhoto
    {
        $size = is_file($file) ? (int) filesize($file) : 0;
        if ($size === 0) {
            throw new PhotoProblem('That file is empty. Choose the photo again.');
        }
        if ($size > self::MAX_BYTES) {
            throw new PhotoProblem(sprintf('That photo is %.0f MB; the most is 20 MB. Use a smaller copy.', $size / 1024 / 1024));
        }

        $info = @getimagesize($file);
        $type = is_array($info) ? $info[2] : null;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if ($type === null || ! isset(self::TYPES[$type]) || $mime !== self::TYPES[$type]) {
            throw new PhotoProblem('That file is not a photo the website can use. Use a JPEG, PNG or WebP photo.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new PhotoProblem('That photo has too many pixels. Use a copy of at most 40 megapixels.');
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            default => @imagecreatefromwebp($file),
        };
        if (! $image instanceof GdImage) {
            throw new PhotoProblem('That photo could not be read. It may be damaged; try saving it again.');
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $file);
        }
        $image = $this->fit($image, self::LONG_EDGE);
        $transparent = $type === IMAGETYPE_PNG && $this->pngHasAlpha($file);

        $bytes = $transparent ? $this->png($image) : $this->jpeg($image, 85);
        $preview = $this->jpeg($this->fit($image, self::PREVIEW_EDGE), 75);

        return new ProcessedPhoto($bytes, $transparent ? 'png' : 'jpg', $preview, imagesx($image), imagesy($image));
    }

    /** Applies the EXIF orientation a phone writes instead of turning the pixels. */
    private function upright(GdImage $image, string $file): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
        $orientation = is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        $rotate = static fn(GdImage $image, int $degrees): GdImage => imagerotate($image, $degrees, 0) ?: $image;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                return $rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 6:
                return $rotate($image, -90);
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 8:
                return $rotate($image, 90);
            default:
                return $image;
        }
    }

    /** At most $edge pixels on the long edge; smaller photos are left as they are. */
    private function fit(GdImage $image, int $edge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) <= $edge) {
            return $image;
        }
        $scale = $edge / max($width, $height);
        $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);

        return $scaled instanceof GdImage ? $scaled : $image;
    }

    /** A PNG's own header says whether it has an alpha channel or a transparent colour. */
    private function pngHasAlpha(string $file): bool
    {
        $head = (string) file_get_contents($file, false, null, 0, 4096);
        $colourType = strlen($head) > 25 ? ord($head[25]) : 0;

        return in_array($colourType, [4, 6], true) || str_contains($head, 'tRNS');
    }

    private function jpeg(GdImage $image, int $quality): string
    {
        // Transparent areas become white, not black.
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        if (! $flat instanceof GdImage) {
            throw new PhotoProblem('The server could not make room for that photo. Try a smaller copy.');
        }
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imageinterlace($flat, true);

        ob_start();
        imagejpeg($flat, null, $quality);

        return (string) ob_get_clean();
    }

    private function png(GdImage $image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}
