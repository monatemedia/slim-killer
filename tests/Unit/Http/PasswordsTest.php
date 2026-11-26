<?php

declare(strict_types=1);

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\PasswordResetLinks;
use Tests\Fakes\Browser;

const SECOND = 'a second long password here';

const THIRD = 'a third long password here';

const FOURTH = 'a fourth long password here';

/** Changes the password on Preferences, signed in. */
function changePassword(Browser $browser, string $current, string $new): int
{
    $browser->get('/cp/preferences');

    return $browser->post('/cp/preferences', ['form' => 'password', 'current_password' => $current, 'password' => $new, 'password_confirmation' => $new])->getStatusCode();
}

it('changes the password on Preferences after the current one, and emails the person', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    enrol($browser, $container, $clock);

    expect(changePassword($browser, 'not my password at all', SECOND))->toBe(422)
        ->and($browser->body())->toContain('That isn&#039;t your current password');

    $done = $browser->post('/cp/preferences', ['form' => 'password', 'current_password' => PASSWORD, 'password' => SECOND, 'password_confirmation' => SECOND]);
    runAfter($after);
    expect($done->getHeaderLine('Location'))->toBe('/cp/preferences?saved=password')
        ->and($browser->get('/cp/preferences?saved=password')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Your password is changed')
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->subject)->toBe('Acme Tours: your password was changed')
        ->and($mailer->sent[0]->text)->toContain('Browser: Safari on an iPhone')
        ->and($container->get(AuditLog::class)->recentEvents())->toContain('password_changed');

    // The new password signs in; the old one doesn't.
    $browser->post('/cp/sign-out', []);
    $browser->get('/cp/sign-in');
    expect($browser->post('/cp/sign-in', ['email' => 'edward@example.com', 'password' => PASSWORD])->getStatusCode())->toBe(422);
    expect($browser->post('/cp/sign-in', ['email' => 'edward@example.com', 'password' => SECOND])->getHeaderLine('Location'))->toBe('/cp/two-factor');
});

it('signs the person out on their other devices when the password changes, but not here', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);
    $laptop = $browser->another(Browser::WINDOWS_CHROME);
    $laptop->get('/cp/sign-in');
    $laptop->post('/cp/sign-in', ['email' => 'edward@example.com', 'password' => PASSWORD]);
    $laptop->get('/cp/two-factor');
    $laptop->post('/cp/two-factor', ['code' => freshCode($secret, $clock)]);
    expect($laptop->get('/cp/')->getStatusCode())->toBe(200);

    changePassword($browser, PASSWORD, SECOND);

    expect($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($laptop->get('/cp/')->getHeaderLine('Location'))->toBe('/cp/sign-in');
});

it('refuses any of the last three passwords, and takes the fourth back', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);

    expect(changePassword($browser, PASSWORD, PASSWORD))->toBe(422)
        ->and($browser->body())->toContain('You have used this password before');

    expect(changePassword($browser, PASSWORD, SECOND))->toBe(303);
    expect(changePassword($browser, SECOND, THIRD))->toBe(303);
    expect(changePassword($browser, THIRD, PASSWORD))->toBe(422);
    expect(changePassword($browser, THIRD, SECOND))->toBe(422);
    expect(changePassword($browser, THIRD, FOURTH))->toBe(303);
    // The first password is now four back: allowed again.
    expect(changePassword($browser, FOURTH, PASSWORD))->toBe(303);
});

it('emails a link for a forgotten password, which leads to a new password and then two-factor', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);
    $browser->post('/cp/sign-out', []);

    $browser->get('/cp/sign-in');
    expect($browser->body())->toContain('href="/cp/forgot-password"');
    $browser->get('/cp/forgot-password');
    $sent = $browser->post('/cp/forgot-password', ['email' => 'Edward@Example.com']);
    runAfter($after);
    expect($sent->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Check your email')
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->subject)->toBe('Acme Tours: choose a new password');

    preg_match('#(/cp/reset-password\?token=[a-f0-9]+)#', $mailer->sent[0]->text, $match);
    expect($browser->get($match[1])->getHeaderLine('Location'))->toBe('/cp/reset-password');
    $browser->get('/cp/reset-password');
    expect($browser->post('/cp/reset-password', ['password' => PASSWORD, 'password_confirmation' => PASSWORD])->getStatusCode())->toBe(422);
    $saved = $browser->post('/cp/reset-password', ['password' => SECOND, 'password_confirmation' => SECOND]);
    runAfter($after);

    expect($saved->getHeaderLine('Location'))->toBe('/cp/two-factor')
        ->and($browser->get('/cp/')->getHeaderLine('Location'))->toBe('/cp/sign-in')
        ->and($mailer->sent[1]->subject)->toBe('Acme Tours: your password was changed');

    $browser->get('/cp/two-factor');
    expect($browser->post('/cp/two-factor', ['code' => freshCode($secret, $clock)])->getHeaderLine('Location'))->toBe('/cp/')
        ->and($browser->get('/cp/')->getStatusCode())->toBe(200);

    // The link worked once.
    expect($browser->another(Browser::WINDOWS_CHROME)->get($match[1])->getStatusCode())->toBe(403);
});

it('answers the same for an address with no account, and sends nothing', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    enrol($browser, $container, $clock);
    $browser->post('/cp/sign-out', []);
    $browser->get('/cp/forgot-password');

    $browser->post('/cp/forgot-password', ['email' => 'nobody@example.com']);
    runAfter($after);

    expect($browser->body())->toContain('Check your email')
        ->and($mailer->sent)->toBe([]);
});

it('lets a forgot-password link expire after an hour', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);
    $browser->post('/cp/sign-out', []);
    $token = $container->get(PasswordResetLinks::class)->issue(1);
    $clock->advance(PasswordResetLinks::LIFETIME_SECONDS);

    expect($browser->get(PasswordResetLinks::pathFor($token))->getStatusCode())->toBe(403)
        ->and($browser->body())->toContain('This link has expired');
});

it('stops asking for links after three for one address', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    enrol($browser, $container, $clock);
    $browser->post('/cp/sign-out', []);
    $browser->get('/cp/forgot-password');

    foreach (range(1, 3) as $_) {
        $browser->post('/cp/forgot-password', ['email' => 'edward@example.com']);
        $browser->get('/cp/forgot-password');
    }
    $fourth = $browser->post('/cp/forgot-password', ['email' => 'edward@example.com']);
    runAfter($after);

    expect($fourth->getStatusCode())->toBe(429)
        ->and($mailer->sent)->toHaveCount(3);
});
