<?php

declare(strict_types=1);

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\BrowserName;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use Tests\Fakes\Browser;
use Tests\Fakes\FakeMailer;
use Tests\Fakes\RecordingAfterResponse;

it('sends no email for the browser an account was set up in, nor when it signs in there again', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);

    expect(signInFrom($browser, freshCode($secret, $clock), $after))->toBe(303)
        ->and($mailer->sent)->toBe([]);
});

it('emails the person when their account signs in from a new browser, once', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);
    $laptop = $browser->another(Browser::WINDOWS_CHROME);

    expect(signInFrom($laptop, freshCode($secret, $clock), $after))->toBe(303)
        ->and($mailer->sent)->toHaveCount(1);

    $letter = $mailer->sent[0];
    expect($letter->to)->toBe('edward@example.com')
        ->and($letter->subject)->toBe('Acme Tours: a new sign-in from Chrome on Windows')
        ->and($letter->text)->toContain('from a browser it hasn\'t used before')
        ->and($letter->text)->toContain('Browser: Chrome on Windows')
        ->and($letter->text)->toContain('IP address: 198.51.100.7')
        ->and($letter->text)->toContain('When: 9 November 2026, 14:')
        ->and($letter->text)->toContain('user:reset --email=edward@example.com')
        ->and($letter->html)->toContain('A new sign-in to your account');
    expect($container->get(AuditLog::class)->recentEvents())->toContain('new_browser');

    // The same laptop again, after signing out: known now, so quiet.
    signInFrom($laptop, freshCode($secret, $clock), $after);
    expect($mailer->sent)->toHaveCount(1);
});

it('always emails when a recovery code is used, with how many are left', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [, $codes] = enrol($browser, $container, $clock);

    expect(signInFrom($browser, $codes[0], $after))->toBe(303)
        ->and($mailer->sent)->toHaveCount(1);

    $letter = $mailer->sent[0];
    expect($letter->subject)->toBe('Acme Tours: a recovery code was used to sign in')
        ->and($letter->text)->toContain('7 recovery codes are left')
        ->and($letter->text)->toContain('Browser: Safari on an iPhone')
        ->and($letter->text)->not->toContain('new for your account');
});

it('forgets an account\'s browsers when it is reset', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);
    $count = static fn(): int => (int) $container->get(PDO::class)->query('SELECT COUNT(*) FROM known_browsers')->fetchColumn();
    expect($count())->toBe(1);

    $users = $container->get(UserRepository::class);
    $users->resetCredentials($users->byEmail('edward@example.com')->id);

    expect($count())->toBe(0);
});

it('sends nothing when the website has no mailbox to send from', function (): void {
    $mailer = new FakeMailer();
    $after = new RecordingAfterResponse();
    [$browser, $container, $clock] = installedPanel([Mailer::class => $mailer, AfterResponse::class => $after, AppConfig::class => new AppConfig()]);
    [$secret] = enrol($browser, $container, $clock);

    expect(signInFrom($browser->another(Browser::WINDOWS_CHROME), freshCode($secret, $clock), $after))->toBe(303)
        ->and($mailer->sent)->toBe([]);
});

it('names browsers the way people know them', function (string $userAgent, string $name): void {
    expect(BrowserName::from($userAgent))->toBe($name);
})->with([
    [Browser::IPHONE, 'Safari on an iPhone'],
    [Browser::WINDOWS_CHROME, 'Chrome on Windows'],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', 'Safari on a Mac'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'Edge on Windows'],
    ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 'Samsung Internet on Android'],
    ['Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox on Linux'],
    ['curl/8.5.0', 'An unknown browser'],
]);
