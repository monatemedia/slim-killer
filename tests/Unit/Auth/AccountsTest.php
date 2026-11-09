<?php

declare(strict_types=1);

use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\InvalidEmailAddress;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Auth\SignInThrottle;
use SlimKiller\Auth\PasswordPolicy;
use SlimKiller\Auth\RecoveryCodes;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserAlreadyExists;
use SlimKiller\Auth\UserRepository;
use Tests\Fakes\SteppingClock;

it('treats an email address the same however it is typed', function (): void {
    expect(EmailAddress::from('  Edward@Example.COM ')->value)->toBe('edward@example.com');
    EmailAddress::from('not an email');
})->throws(InvalidEmailAddress::class);

it('creates users, once per email address', function (): void {
    $users = new UserRepository(migratedDatabase(), new SteppingClock('2026-11-09'));

    expect($users->any())->toBeFalse();
    $user = $users->create(EmailAddress::from('edward@example.com'), 'Edward', Role::Admin);

    expect($users->any())->toBeTrue()
        ->and($users->byEmail('EDWARD@example.com')?->id)->toBe($user->id)
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->hasTwoFactor())->toBeFalse();

    $users->create(EmailAddress::from('edward@example.com'), 'Edward again', Role::Owner);
})->throws(UserAlreadyExists::class);

it('says there is nobody before the database exists', function (): void {
    expect((new UserRepository(SlimKiller\Database\Database::open(':memory:'), new SteppingClock('2026-11-09')))->any())->toBeFalse();
});

it('keeps one welcome link per person, for 72 hours', function (): void {
    $pdo = migratedDatabase();
    $clock = new SteppingClock('2026-11-09 12:00');
    $user = (new UserRepository($pdo, $clock))->create(EmailAddress::from('client@example.com'), 'Client', Role::Owner);
    $welcomeLinks = new WelcomeLinks($pdo, $clock);

    $first = $welcomeLinks->issue($user->id);
    $second = $welcomeLinks->issue($user->id);

    expect($welcomeLinks->userFor($first))->toBeNull()
        ->and($welcomeLinks->userFor($second))->toBe($user->id)
        ->and(WelcomeLinks::pathFor($second))->toBe('/cp/welcome?token=' . $second);

    $clock->advance(72 * 3600);
    expect($welcomeLinks->userFor($second))->toBeNull()
        ->and($welcomeLinks->isOpenFor($user->id))->toBeFalse();
});

it('lets each recovery code be used once', function (): void {
    $pdo = migratedDatabase();
    $clock = new SteppingClock('2026-11-09');
    $user = (new UserRepository($pdo, $clock))->create(EmailAddress::from('client@example.com'), 'Client', Role::Owner);
    $codes = new RecoveryCodes($pdo, $clock);

    $issued = $codes->replace($user->id);

    expect($issued)->toHaveCount(8)
        ->and(RecoveryCodes::looksLikeOne($issued[0]))->toBeTrue()
        ->and($codes->use($user->id, strtoupper($issued[0])))->toBeTrue()
        ->and($codes->use($user->id, $issued[0]))->toBeFalse()
        ->and($codes->use($user->id, 'aaaaa-bbbbb'))->toBeFalse()
        ->and($codes->remaining($user->id))->toBe(7);
});

it('makes a key wait after too many failures, and forgets them after the window', function (): void {
    $clock = new SteppingClock('2026-11-09 12:00');
    $throttle = new SignInThrottle(migratedDatabase(), $clock);

    foreach (range(1, 4) as $_) {
        $throttle->fail('email:a@example.com');
    }
    expect($throttle->waitFor(['email:a@example.com' => 5]))->toBe(0);

    $throttle->fail('email:a@example.com');
    expect($throttle->waitFor(['email:a@example.com' => 5, 'ip:1.2.3.4' => 20]))->toBe(15 * 60);

    $clock->advance(15 * 60);
    expect($throttle->waitFor(['email:a@example.com' => 5]))->toBe(0);
});

it('wants a long password, typed the same twice', function (): void {
    expect(PasswordPolicy::problem('short', 'short'))->toContain('at least 12')
        ->and(PasswordPolicy::problem('correct horse battery', 'correct horse batter'))->toContain('not the same')
        ->and(PasswordPolicy::problem('correct horse battery', 'correct horse battery'))->toBeNull();
});
