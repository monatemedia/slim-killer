<?php

declare(strict_types=1);

/*
 * The control panel's JavaScript in a real browser: WebKit (Safari's engine) at the size
 * of an iPhone 7 (375 × 667), the phone the panel is tried on.
 */

/**
 * Every visible field, box, button and photo that reaches past the edge of the screen.
 * The card clips what does not fit (overflow: hidden), so a form that is too wide looks
 * cut off rather than scrolling sideways: measuring the page alone would miss it.
 */
const OFF_SCREEN = <<<'JS'
    [...document.querySelectorAll('main fieldset, main .field, main input, main textarea, main select, main .btn, main img')]
        .filter((element) => element.getClientRects().length > 0 && !element.closest('.visually-hidden, dialog:not([open])') && !element.classList.contains('visually-hidden'))
        .filter((element) => { const box = element.getBoundingClientRect(); return box.width > 1 && (box.left < -0.5 || box.right > window.innerWidth + 0.5); })
        .map((element) => element.tagName.toLowerCase() + (element.name ? '[name=' + element.name + ']' : '.' + element.className))
    JS;

it('fits the screen of an iPhone 7: nothing cut off at the edge, nothing to scroll sideways', function (): void {
    $page = onIPhone7('/cp/content/articles/oryx')->assertSee('The Oryx');

    expect($page->script(OFF_SCREEN))->toBe([]);
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

it('shows a picked photo at once, and asks for a new description', function (): void {
    $page = pickFile(onIPhone7('/cp/content/articles/oryx'), '[name="upload[gallery.0.src]"]', jpegPhoto(800, 600));

    $field = '.photo-field:has([name="upload[gallery.0.src]"])';
    $page->assertSeeIn($field . ' [data-photo-file]', 'New photo, added when you press Save')
        ->assertAttributeContains($field . ' [data-photo-preview]', 'src', 'blob:')
        ->assertValue('[name="f[gallery][0][alt]"]', '')
        ->assertAttribute('[name="f[gallery][0][alt]"]', 'placeholder', 'Was: An oryx at a waterhole')
        ->assertVisible('.field-nudge')
        ->assertNoJavaScriptErrors();

    // Typing a description takes the warning away.
    $page->type('[name="f[gallery][0][alt]"]', 'An oryx in the dunes')
        ->assertScript("document.querySelector('.needs-description') === null", true);
});

it('chooses one of the site\'s photos from the chooser', function (): void {
    onIPhone7('/cp/content/articles/oryx')
        ->assertVisible('.photo-field:has(#f-heroImage) [data-photo-choose]')
        ->click('.photo-field:has(#f-heroImage) [data-photo-choose]')
        ->assertVisible('#photo-picker [data-file="deadvlei.jpg"]')
        ->click('#photo-picker [data-file="deadvlei.jpg"]')
        ->assertScript("document.getElementById('photo-picker').open", false)
        ->assertValue('[name="f[heroImage]"]', 'deadvlei.jpg')
        ->assertSeeIn('.photo-field:has(#f-heroImage) [data-photo-file]', 'deadvlei.jpg')
        ->assertNoJavaScriptErrors();
});

it('keeps a field\'s tip on the screen when it opens', function (): void {
    onIPhone7('/cp/content/articles/oryx')
        ->assertVisible('[aria-label="What is Description for search results?"]')
        ->click('[aria-label="What is Description for search results?"]')
        ->assertVisible('.field-tip[open] p')
        ->assertScript(
            "(() => { const r = document.querySelector('.field-tip[open] p').getBoundingClientRect(); return r.left >= 0 && r.right <= window.innerWidth; })()",
            true,
        );
});
