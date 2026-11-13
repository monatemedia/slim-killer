<?php

declare(strict_types=1);

/*
 * The panel's frame on a phone: the header (logo, name, publishing chip, theme switch),
 * focus rings, and how much of a small screen the form gets.
 */

afterEach(function (): void {
    publishingReport(null);
});

it('shows publishing as a small chip under the logo, with the steps behind it', function (): void {
    publishingReport(['stage' => 'building', 'message' => 'Your website is being built.', 'done' => false]);

    $page = onIPhone7('/cp/')
        ->assertVisible('header [data-publishing] summary')
        ->assertSeeIn('header [data-publishing] summary', 'Building');
    expect($page->script("document.querySelector('header [data-publishing]').getBoundingClientRect().height"))->toBeLessThan(48);

    $page->click('header [data-publishing] summary')
        ->assertVisible('header [data-publishing] [data-step="building"]')
        ->assertSeeIn('header [data-publishing]', 'Your website is being built.');
});

it('says Live on the chip when the change is on the website', function (): void {
    publishingReport(['stage' => 'live', 'message' => 'Your change is live.', 'done' => true]);

    onIPhone7('/cp/')
        ->assertVisible('header [data-publishing] summary')
        ->assertSeeIn('header [data-publishing] summary', 'Live');
});

it('shows no chip when nothing is being published', function (): void {
    onIPhone7('/cp/')
        ->assertVisible('header .brand')
        ->assertMissing('header [data-publishing] summary');
});

it('takes the logo and the name together to the dashboard', function (): void {
    onIPhone7('/cp/content/articles/oryx')
        ->assertVisible('header a.brand img')
        ->assertSeeIn('header a.brand', 'Slim')
        ->assertSeeIn('header a.brand', 'Killer')
        ->assertAttribute('header a.brand', 'href', '/cp/');
});

it('follows the phone\'s dark mode until switched, then remembers the choice', function (): void {
    $background = "getComputedStyle(document.body).backgroundColor";
    $page = onIPhone7('/cp/', dark: true)->assertVisible('[data-theme-switch]');
    expect($page->script($background))->toBe('rgb(21, 23, 28)');

    $page->click('[data-theme-switch]');
    expect($page->script($background))->toBe('rgb(242, 242, 242)');

    $page->refresh()->assertVisible('[data-theme-switch]');
    expect($page->script($background))->toBe('rgb(242, 242, 242)');
});

it('draws a rounded focus ring on a field, not a square outline', function (): void {
    $page = onIPhone7('/cp/content/articles/oryx')->assertVisible('[name="f[title]"]');
    $page->script("document.querySelector('[name=\"f[title]\"]').focus()");

    expect($page->script("(() => { const s = getComputedStyle(document.querySelector('[name=\"f[title]\"]')); return [s.outlineStyle, s.boxShadow.includes('rgb') ? 'ring' : 'none', parseFloat(s.borderTopLeftRadius) > 0 ? 'round' : 'square']; })()"))
        ->toBe(['none', 'ring', 'round']);
});

it('gives the form most of a small screen\'s width', function (): void {
    $page = onIPhone7('/cp/content/articles/oryx')->assertVisible('[name="f[title]"]');
    $width = static fn(string $field): float => (float) $page->script("document.querySelector('[name=\"{$field}\"]').getBoundingClientRect().width");

    // Was 297 px and 263 px: page, card and group margins added up on a 375 px phone.
    expect($width('f[title]'))->toBeGreaterThanOrEqual(315)
        ->and($width('f[gallery][0][alt]'))->toBeGreaterThanOrEqual(290);
});
