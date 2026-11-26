<?php

declare(strict_types=1);

/*
 * Preferences on a phone: every password field's eye button shows its own field, and
 * the password boxes the browser likes to fill in start empty.
 */

it('shows and hides each password field with its own eye button', function (string $field): void {
    $page = onIPhone7('/cp/preferences')
        ->assertVisible("[data-reveal=\"{$field}\"]");
    expect($page->script("document.getElementById('{$field}').type"))->toBe('password');

    $page->click("[data-reveal=\"{$field}\"]");
    expect($page->script("document.getElementById('{$field}').type"))->toBe('text');

    $page->click("[data-reveal=\"{$field}\"]");
    expect($page->script("document.getElementById('{$field}').type"))->toBe('password');
})->with(['current_password', 'password', 'password_confirmation', 'two_factor_password']);

it('gives every element on Preferences its own id, so each button finds its field', function (): void {
    $page = onIPhone7('/cp/preferences')->assertVisible('#two-factor');

    expect($page->script("(() => { const ids = [...document.querySelectorAll('[id]')].map(e => e.id); return ids.length - new Set(ids).size; })()"))->toBe(0);
});

it('empties a password the browser filled in, but keeps what the person types', function (): void {
    $page = onIPhone7('/cp/preferences')->assertVisible('#two_factor_password');

    // What a browser's autofill does: a value, and an input event, with no keys pressed.
    $page->script("(() => { const f = document.getElementById('two_factor_password'); f.value = 'saved password'; f.dispatchEvent(new Event('input', {bubbles: true})); })()");
    $page->wait(1.2);
    expect($page->script("document.getElementById('two_factor_password').value"))->toBe('');

    $page->typeSlowly('#current_password', 'typed by hand', 10);
    $page->wait(2.2);
    expect($page->script("document.getElementById('current_password').value"))->toBe('typed by hand');
});
