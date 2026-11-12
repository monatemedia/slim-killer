<?php

declare(strict_types=1);

use SlimKiller\Content\ContentLocation;
use SlimKiller\Photos\PhotoLibrary;
use SlimKiller\Photos\PhotoProcessor;
use SlimKiller\Photos\StagedPhotos;
use SlimKiller\Support\Paths;

/** @return array{0: StagedPhotos, 1: PhotoLibrary, 2: string} */
function stagingArea(array $builtPhotos = []): array
{
    $root = temporaryFolder();
    $paths = new Paths($root);
    mkdir($root . '/storage/site', 0755, true);
    $previews = [];
    foreach ($builtPhotos as $file) {
        $previews[$file] = ['preview' => substr(sha1($file), 0, 16) . '.webp', 'width' => 1, 'height' => 1];
    }
    file_put_contents($root . '/storage/site/previews.json', json_encode($previews));
    $library = new PhotoLibrary(new ContentLocation($paths->storage('site/content'), $paths->storage('site/schemas')), $paths);

    return [new StagedPhotos($paths, $library), $library, $root];
}

it('names a photo after the file it came from, and never over one the site has', function (): void {
    [$staged] = stagingArea(['lions-at-dusk.jpg']);
    $photo = (new PhotoProcessor())->process(jpegPhoto(300, 200));

    expect($staged->stage(7, $photo, 'Lions at Dusk.JPG'))->toBe('lions-at-dusk-2.jpg')
        ->and($staged->stage(7, $photo, 'Lions at Dusk.JPG'))->toBe('lions-at-dusk-3.jpg')
        ->and($staged->stage(7, $photo, '.jpg'))->toBe('photo.jpg')
        ->and($staged->stage(7, $photo, 'Ünïcödé 😀.jpeg'))->toBe('n-c-d.jpg');
});

it('keeps each person\'s photos to themselves, and nothing outside the folder', function (): void {
    [$staged] = stagingArea();
    $file = $staged->stage(7, (new PhotoProcessor())->process(jpegPhoto(300, 200)), 'oryx.jpg');

    expect($staged->has(7, $file))->toBeTrue()
        ->and($staged->has(8, $file))->toBeFalse()
        ->and($staged->has(7, '../7/' . $file))->toBeFalse()
        ->and($staged->all(7))->toBe([$file => PhotoLibrary::previewName($file)]);
});

it('moves a saved photo\'s preview into the library, so it shows before the next build', function (): void {
    [$staged, $library] = stagingArea();
    $file = $staged->stage(7, (new PhotoProcessor())->process(jpegPhoto(300, 200)), 'oryx.jpg');

    $staged->saved(7, $file);

    expect($staged->has(7, $file))->toBeFalse()
        ->and($library->has($file))->toBeTrue()
        ->and($library->previewPath(PhotoLibrary::previewName($file)))->toBeFile()
        ->and($library->previewPath('../../etc/passwd'))->toBeNull();
});
