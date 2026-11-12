<?php

declare(strict_types=1);

use SlimKiller\Content\ContentLocation;
use SlimKiller\Content\PageAddresses;

it('reads the addresses beside the working copy', function (): void {
    $site = temporaryFolder();
    file_put_contents($site . '/addresses.json', '{"destinations/namibia": "/destinations/namibia/"}');

    $addresses = new PageAddresses(new ContentLocation($site . '/content', $site . '/schemas'));

    expect($addresses->of('destinations', 'namibia'))->toBe('/destinations/namibia/')
        ->and($addresses->of('destinations', 'botswana'))->toBeNull();
});

it('reads a development site\'s last build when there is no copy beside the content', function (): void {
    $site = temporaryFolder();
    mkdir($site . '/site/dist/_slim-killer', 0755, true);
    file_put_contents($site . '/site/dist/_slim-killer/addresses.json', '{"home/index": "/"}');

    $addresses = new PageAddresses(new ContentLocation($site . '/content', $site . '/site/.astro/collections'));

    expect($addresses->of('home', 'index'))->toBe('/');
});

it('knows no addresses without the file, and ignores anything that is not a path on the site', function (): void {
    $site = temporaryFolder();
    $location = new ContentLocation($site . '/content', $site . '/schemas');
    expect((new PageAddresses($location))->of('home', 'index'))->toBeNull();

    file_put_contents($site . '/addresses.json', json_encode([
        'a/one' => 'https://elsewhere.example/',
        'a/two' => '//elsewhere.example/',
        'a/three' => 'javascript:alert(1)',
        'a/four' => '/fine/',
        'a/five' => 5,
    ]));
    $addresses = new PageAddresses($location);

    expect(array_map(fn(string $slug): ?string => $addresses->of('a', $slug), ['one', 'two', 'three', 'four', 'five']))
        ->toBe([null, null, null, '/fine/', null]);
});
