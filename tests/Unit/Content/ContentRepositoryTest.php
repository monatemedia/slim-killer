<?php

declare(strict_types=1);

use SlimKiller\Content\ContentLocation;
use SlimKiller\Content\ContentRepository;
use SlimKiller\Content\GitBlob;
use SlimKiller\Content\Labels;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Paths;

function fixtureContent(): ContentRepository
{
    $site = dirname(__DIR__, 2) . '/Fixtures/site';

    return new ContentRepository(new ContentLocation($site . '/content', $site . '/schemas'));
}

it('finds the collections that have both a schema and a content folder', function (): void {
    $names = array_map(fn($c) => $c->name, fixtureContent()->collections());

    expect($names)->toBe(['articles', 'pages']);
});

it('lists a collection\'s entries by title, skipping files that start with an underscore', function (): void {
    $repository = fixtureContent();
    $titles = array_map(fn($e) => $e->title, $repository->entries($repository->collection('articles')));

    expect($titles)->toBe(['Deadvlei', 'The Oryx']);
});

it('reads an entry: its values, its body and its version', function (): void {
    $repository = fixtureContent();
    $entry = $repository->entry($repository->collection('articles'), 'oryx');
    $raw = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/site/content/articles/oryx.md');

    expect($entry->data['heroImage'])->toBe('oryx.jpg')
        ->and($entry->data['gallery'][1]['src'])->toBe('oryx-2.jpg')
        ->and($entry->body)->toStartWith("\n## In the desert")
        ->and($entry->version)->toBe(GitBlob::hash($raw));
});

it('refuses names that could reach outside the content folder', function (string $slug): void {
    $repository = fixtureContent();

    expect($repository->entry($repository->collection('articles'), $slug))->toBeNull()
        ->and($repository->collection($slug))->toBeNull();
})->with(['../pages/about', '..', '_template', 'oryx.md/..', '']);

it('reads its folders from .env, relative to the project, or uses storage by default', function (): void {
    $paths = new Paths('/srv/site');

    $default = ContentLocation::from(new AppConfig(), $paths);
    $configured = ContentLocation::from(new AppConfig(contentDir: '../sandbox/src/content', schemaDir: '/abs/schemas'), $paths);

    expect($default->contentDir)->toBe('/srv/site/storage/site/content')
        ->and($configured->contentDir)->toBe('/srv/site/../sandbox/src/content')
        ->and($configured->schemaDir)->toBe('/abs/schemas');
});

it('makes labels from names in code', function (string $name, string $label): void {
    expect(Labels::fromName($name))->toBe($label);
})->with([
    ['heroImageAlt', 'Hero image alt'],
    ['tour-categories', 'Tour categories'],
    ['ogImage', 'Og image'],
]);
