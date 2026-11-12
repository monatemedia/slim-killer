<?php

declare(strict_types=1);

use Slim\Psr7\UploadedFile;

/**
 * Test photos, made with GD. A JPEG can carry an EXIF orientation (as a phone writes it)
 * and anything appended after the image data.
 */
function jpegPhoto(int $width, int $height, int $orientation = 1, string $appended = ''): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 120, 40));
    // A white mark in the top-left corner, to see which way up the result is.
    imagefilledrectangle($image, 0, 0, intdiv($width, 4), intdiv($height, 4), (int) imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = (string) ob_get_clean();

    if ($orientation !== 1) {
        $tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, $orientation) . "\0\0" . pack('V', 0);
        $app1 = "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff;
        $bytes = substr($bytes, 0, 2) . $app1 . substr($bytes, 2);
    }

    return photoFile($bytes . $appended, 'jpg');
}

function pngPhoto(int $width, int $height, bool $transparent): string
{
    $image = imagecreatetruecolor($width, $height);
    if ($transparent) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    } else {
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 10, 120, 200));
    }
    ob_start();
    imagepng($image);

    return photoFile((string) ob_get_clean(), 'png');
}

function photoFile(string $bytes, string $extension): string
{
    $path = temporaryFolder() . '/upload.' . $extension;
    file_put_contents($path, $bytes);

    return $path;
}

/** A file as PHP hands an upload to the panel. */
function uploaded(string $path, string $name): UploadedFile
{
    return new UploadedFile($path, $name, null, (int) filesize($path));
}
