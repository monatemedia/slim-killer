<?php

declare(strict_types=1);

/*
 * The focus point: tap the part of the photo that must stay in view when the website
 * crops it. Stored as "x% y%", which the site uses as the photo's object-position.
 */

const FIRST = '.list-item:has([name="f[gallery][0][src]"])';
const SECOND = '.list-item:has([name="f[gallery][1][src]"])';

/** The style the dot and the two crop previews have, and the stored value, as one string. */
function focusState(mixed $page, string $item): string
{
    return (string) $page->script(sprintf(
        "(() => { const item = document.querySelector(%s);
            const dot = item.querySelector('[data-focus-dot]');
            const crops = [...item.querySelectorAll('[data-focus-crop]')].map((crop) => crop.style.objectPosition);
            return [item.querySelector('[name$=\"[focus]\"]').value, dot.style.left + ' ' + dot.style.top, ...crops].join(' | '); })()",
        json_encode($item),
    ));
}

it('shows the focus point already saved as a dot on the photo, and hides the text box', function (): void {
    $page = onIPhone7('/cp/content/articles/oryx');

    expect(focusState($page, FIRST))->toBe('center 30% | 50% 30% | 50% 30% | 50% 30%');
    $page->assertAttribute('[name="f[gallery][0][focus]"]', 'type', 'hidden')
        ->assertVisible(FIRST . ' [data-focus-frame]')
        ->assertNoJavaScriptErrors();
});

it('moves the focus point to where the photo is tapped, and shows both crops', function (): void {
    $page = tapAt(onIPhone7('/cp/content/articles/oryx'), FIRST . ' [data-focus-frame]', 25, 75);

    expect(focusState($page, FIRST))->toBe('25% 75% | 25% 75% | 25% 75% | 25% 75%');
});

it('moves the focus point with the arrow keys too', function (): void {
    $page = pressKeyOn(onIPhone7('/cp/content/articles/oryx'), FIRST . ' [data-focus-frame]', 'ArrowRight');
    pressKeyOn($page, FIRST . ' [data-focus-frame]', 'ArrowUp');

    expect(focusState($page, FIRST))->toBe('55% 25% | 55% 25% | 55% 25% | 55% 25%');
});

it('puts the focus point back in the middle with Reset', function (): void {
    $page = onIPhone7('/cp/content/articles/oryx')
        ->assertPresent(FIRST . ' [data-focus-reset]')
        ->click(FIRST . ' [data-focus-reset]');

    expect(focusState($page, FIRST))->toBe(' | 50% 50% | 50% 50% | 50% 50%');
});

it('starts a new photo with its focus point in the middle', function (): void {
    $page = pickFile(onIPhone7('/cp/content/articles/oryx'), '[name="upload[gallery.0.src]"]', jpegPhoto(800, 600));

    expect(focusState($page, FIRST))->toBe(' | 50% 50% | 50% 50% | 50% 50%');
    $page->assertAttributeContains(FIRST . ' [data-focus-frame] img', 'src', 'blob:');
});

it('changes only its own photo\'s description and focus point in a gallery', function (): void {
    $page = pickFile(onIPhone7('/cp/content/articles/oryx'), '[name="upload[gallery.1.src]"]', jpegPhoto(800, 600));

    $page->assertValue('[name="f[gallery][0][alt]"]', 'An oryx at a waterhole')
        ->assertVisible(SECOND . ' .field-nudge');
    expect(focusState($page, FIRST))->toStartWith('center 30% |');
});
