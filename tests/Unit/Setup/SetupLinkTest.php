<?php

declare(strict_types=1);

use SlimKiller\Setup\SetupLink;
use SlimKiller\Support\Paths;
use Tests\Fakes\FrozenClock;

it('accepts the link it issued, and nothing else', function (): void {
    $link = new SetupLink(new Paths(temporaryFolder()), FrozenClock::at('2026-11-09 12:00:00'));
    $token = $link->issue();

    expect($link->accepts($token))->toBeTrue()
        ->and($link->accepts(strrev($token)))->toBeFalse()
        ->and($link->accepts(''))->toBeFalse();
});

it('stops working after an hour', function (): void {
    $paths = new Paths(temporaryFolder());
    $token = (new SetupLink($paths, FrozenClock::at('2026-11-09 12:00:00')))->issue();

    expect((new SetupLink($paths, FrozenClock::at('2026-11-09 12:59:59')))->accepts($token))->toBeTrue()
        ->and((new SetupLink($paths, FrozenClock::at('2026-11-09 13:00:00')))->accepts($token))->toBeFalse();
});

it('replaces the last link with each new one', function (): void {
    $link = new SetupLink(new Paths(temporaryFolder()), FrozenClock::at('2026-11-09 12:00:00'));
    $first = $link->issue();
    $second = $link->issue();

    expect($link->accepts($first))->toBeFalse()
        ->and($link->accepts($second))->toBeTrue();
});

it('refuses every token before a link was issued', function (): void {
    $link = new SetupLink(new Paths(temporaryFolder()), FrozenClock::at('2026-11-09 12:00:00'));

    expect($link->accepts(str_repeat('a', 32)))->toBeFalse();
});

it('stores only a hash, so the storage folder does not give the link away', function (): void {
    $paths = new Paths(temporaryFolder());
    $token = (new SetupLink($paths, FrozenClock::at('2026-11-09 12:00:00')))->issue();

    expect((string) file_get_contents($paths->storage(SetupLink::FILE)))->not->toContain($token)
        ->and(SetupLink::pathFor($token))->toBe('/cp/setup?token=' . $token);
});
