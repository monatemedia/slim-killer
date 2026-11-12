<?php

declare(strict_types=1);

use SlimKiller\Photos\PhotoProblem;
use SlimKiller\Photos\PhotoProcessor;

function photoSize(string $bytes): array
{
    $info = getimagesizefromstring($bytes);

    return [$info[0], $info[1], $info['mime']];
}

it('makes a large photo at most 2400 px on the long edge, as a JPEG, with a 400 px preview', function (): void {
    $photo = (new PhotoProcessor())->process(jpegPhoto(3000, 2000));

    expect(photoSize($photo->bytes))->toBe([2400, 1600, 'image/jpeg'])
        ->and($photo->extension)->toBe('jpg')
        ->and(photoSize($photo->preview))->toBe([400, 267, 'image/jpeg'])
        ->and([$photo->width, $photo->height])->toBe([2400, 1600]);
});

it('leaves a small photo its size', function (): void {
    expect(photoSize((new PhotoProcessor())->process(jpegPhoto(800, 600))->bytes))->toBe([800, 600, 'image/jpeg']);
});

it('turns a phone photo the right way up, and drops its EXIF', function (): void {
    // Orientation 6: the camera was turned; the pixels lie on their side.
    $photo = (new PhotoProcessor())->process(jpegPhoto(400, 200, orientation: 6));

    expect(photoSize($photo->bytes))->toBe([200, 400, 'image/jpeg'])
        ->and($photo->bytes)->not->toContain('Exif');
});

it('keeps a transparent PNG as PNG, and makes an opaque one a JPEG', function (): void {
    $processor = new PhotoProcessor();

    expect($processor->process(pngPhoto(300, 200, transparent: true))->extension)->toBe('png')
        ->and($processor->process(pngPhoto(300, 200, transparent: false))->extension)->toBe('jpg');
});

it('keeps nothing hidden after the image, such as PHP code', function (): void {
    $photo = (new PhotoProcessor())->process(jpegPhoto(300, 200, appended: '<?php system($_GET["c"]); ?>'));

    expect($photo->bytes)->not->toContain('<?php')->and($photo->preview)->not->toContain('<?php');
});

it('refuses what is not a photo it can use', function (string $bytes, string $message): void {
    expect(fn() => (new PhotoProcessor())->process(photoFile($bytes, 'jpg')))->toThrow(PhotoProblem::class, $message);
})->with([
    'empty' => ['', 'empty'],
    'text named .jpg' => ['<?php echo "hello"; ?>', 'not a photo'],
    'a GIF' => [base64_decode('R0lGODlhAQABAAAAACw='), 'not a photo'],
]);

it('refuses a file over 20 MB before reading it', function (): void {
    $path = photoFile('', 'jpg');
    $handle = fopen($path, 'r+');
    ftruncate($handle, PhotoProcessor::MAX_BYTES + 1);
    fclose($handle);

    expect(fn() => (new PhotoProcessor())->process($path))->toThrow(PhotoProblem::class, 'the most is 20 MB');
});
