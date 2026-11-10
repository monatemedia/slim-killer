<?php

declare(strict_types=1);

use SlimKiller\Support\AppConfig;

/** A signed-in panel reading the fixture site. */
function contentPanel(): array
{
    $site = dirname(__DIR__, 2) . '/Fixtures/site';
    [$browser, $container, $clock] = installedPanel([
        AppConfig::class => new AppConfig(contentDir: $site . '/content', schemaDir: $site . '/schemas'),
    ]);
    enrol($browser, $container, $clock);

    return [$browser, $container, $clock];
}

it('keeps content pages for signed-in people', function (string $path): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);

    expect($browser->get($path)->getHeaderLine('Location'))->toBe('/cp/sign-in');
})->with(['/cp/content', '/cp/content/articles', '/cp/content/articles/oryx']);

it('lists the collections, then a collection\'s pages', function (): void {
    [$browser] = contentPanel();

    expect($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('href="/cp/content"');

    $browser->get('/cp/content');
    expect($browser->body())->toContain('Articles')->toContain('2 pages')->toContain('Pages')->not->toContain('Products');

    $browser->get('/cp/content/articles');
    expect($browser->body())->toContain('href="/cp/content/articles/oryx"')->toContain('Deadvlei')->not->toContain('Template');
});

it('shows a page\'s form, built from the schema and filled with its values', function (): void {
    [$browser] = contentPanel();

    $response = $browser->get('/cp/content/articles/oryx');
    $body = $browser->body();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain('Page title')
        ->toContain('value="The Oryx"')
        ->toContain('One or two sentences.')
        ->toContain('value="oryx-2.jpg"')
        ->toContain('The oryx can go for _weeks_ without water.')
        ->toContain('name="f[gallery][1][src]"')
        ->toContain('maxlength="20"')
        ->toContain('Web address: <code>…/oryx/</code>')
        ->not->toContain('style=');
});

it('says a page is not there, rather than guessing', function (string $path): void {
    [$browser] = contentPanel();

    expect($browser->get($path)->getStatusCode())->toBe(404);
})->with(['/cp/content/nope', '/cp/content/articles/nope', '/cp/content/articles/_template', '/cp/content/products']);

it('adds each file\'s change time to its address, so browsers do not keep an old copy', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content');

    expect($browser->body())->toMatch('#href="/cp/assets/cp\\.css\\?v=\\d+"#')
        ->toMatch('#src="/cp/assets/cp\\.js\\?v=\\d+"#');
});

it('checks a posted page against the schema and shows each problem beside its field', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    $response = $browser->post('/cp/content/articles/oryx', [
        'f' => [
            'title' => 'A title much longer than twenty characters',
            'description' => '',
            'heroImage' => 'oryx.jpg',
        ],
        'body' => 'Text',
    ]);
    $body = $browser->body();

    expect($response->getStatusCode())->toBe(422)
        ->and($body)->toContain('2 things need changing')
        ->toContain('Page title can have up to 20 characters. This has 42.')
        ->toContain('Description for search results is needed.')
        ->toContain('href="#f-description"')
        ->toContain('value="A title much longer than twenty characters"');
});

it('accepts a page that matches the schema', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    $response = $browser->post('/cp/content/articles/oryx', [
        'f' => ['title' => 'The Oryx', 'description' => 'Namibia\'s national animal.', 'heroImage' => 'oryx.jpg'],
        'body' => 'Text',
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Everything is filled in correctly');
});

it('refuses a posted page without the form\'s token', function (): void {
    [$browser] = contentPanel();
    $browser->get('/cp/content/articles/oryx');

    expect($browser->post('/cp/content/articles/oryx', ['f' => [], '_csrf' => 'wrong'])->getStatusCode())->toBe(403);
});
