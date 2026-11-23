<?php

declare(strict_types=1);

use SlimKiller\Auth\UserRepository;
use SlimKiller\Auth\WebsiteBar;
use SlimKiller\Content\ContentLocation;

/** A signed-in panel whose site has a home page and an about page. */
function barPanel(): array
{
    [$browser, $container, $clock] = installedPanel();
    $site = dirname($container->get(ContentLocation::class)->contentDir);
    @mkdir($site, 0755, true);
    file_put_contents($site . '/addresses.json', '{"home/index": "/", "pages/about": "/about/"}');
    enrol($browser, $container, $clock);

    return [$browser, $container];
}

it('tells the website bar nobody is signed in, and nothing else', function (): void {
    [$browser] = installedPanel();

    $response = $browser->get('/cp/me?path=/about/');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toContain('application/json')
        ->and(json_decode($browser->body(), true))->toBe(['signedIn' => false]);
});

it('tells the website bar who is signed in, and where to edit the page they are on', function (): void {
    [$browser] = barPanel();

    $browser->get('/cp/me?path=/about/');
    $me = json_decode($browser->body(), true);

    expect($me['signedIn'])->toBeTrue()
        ->and($me['name'])->toBe('Edward')
        ->and($me['bar'])->toBe('show')
        ->and($me['edit'])->toBe('/cp/content/pages/about')
        ->and($me['dashboard'])->toBe('/cp/');

    $browser->get('/cp/me?path=/not-a-content-page/');

    expect(json_decode($browser->body(), true)['edit'])->toBeNull();
});

it('marks this browser as signed in on the dashboard, and takes the mark away on the sign-in page', function (): void {
    [$browser] = barPanel();

    $browser->get('/cp/');
    expect($browser->body())->toContain('data-editor-bar="show"')
        ->and($browser->body())->toContain('href="/cp/preferences"');

    $browser->post('/cp/sign-out', []);
    $browser->get('/cp/sign-in');

    expect($browser->body())->toContain('data-editor-bar="out"');
});

it('keeps each person\'s choice of seeing the website bar', function (): void {
    [$browser, $container] = barPanel();

    $browser->get('/cp/preferences');
    expect($browser->body())->toContain('value="show" checked');

    $saved = $browser->post('/cp/preferences', ['website_bar' => 'hide']);
    expect($saved->getHeaderLine('Location'))->toBe('/cp/preferences?saved=1');

    $browser->get('/cp/preferences?saved=1');
    expect($browser->body())->toContain('value="hide" checked')
        ->and($browser->body())->toContain('data-editor-bar="hide"')
        ->and($container->get(UserRepository::class)->byEmail('edward@example.com')?->websiteBar)->toBe(WebsiteBar::Hide);

    $browser->get('/cp/me?path=/');
    expect(json_decode($browser->body(), true)['bar'])->toBe('hide');
});

it('ignores a choice it does not know', function (): void {
    [$browser, $container] = barPanel();
    $browser->get('/cp/preferences');

    $browser->post('/cp/preferences', ['website_bar' => 'everywhere']);

    expect($container->get(UserRepository::class)->byEmail('edward@example.com')?->websiteBar)->toBe(WebsiteBar::Show);
});

it('keeps preferences for signed-in people', function (): void {
    [$browser] = installedPanel();

    expect($browser->get('/cp/preferences')->getHeaderLine('Location'))->toBe('/cp/sign-in');
});

it('offers "Don\'t count this browser" on the Visits page', function (): void {
    [$browser] = barPanel();

    $browser->get('/cp/visits');

    expect($browser->body())->toContain('data-dont-count');
});
