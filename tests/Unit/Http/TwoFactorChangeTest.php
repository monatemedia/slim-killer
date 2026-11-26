<?php

declare(strict_types=1);

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\Totp;
use SlimKiller\Http\ControlPanel\Actions\TwoFactorChangeAction;
use Tests\Fakes\Browser;
use Tests\Fakes\SteppingClock;

/** Sends the Two-factor form on Preferences. */
function twoFactorForm(Browser $browser, string $task, string $password, string $code): int
{
    $browser->get('/cp/preferences');

    return $browser->post('/cp/preferences', ['form' => 'two-factor', 'task' => $task, 'two_factor_password' => $password, 'code' => $code])->getStatusCode();
}

function newAppSecret(Browser $browser): string
{
    preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $browser->body(), $match);

    return str_replace(' ', '', $match[1] ?? '');
}

function codeFor(string $secret, SteppingClock $clock): string
{
    return (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp());
}

it('makes new recovery codes after the password and a code, and the old ones stop working', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [$secret, $oldCodes] = enrol($browser, $container, $clock);

    expect(twoFactorForm($browser, 'codes', PASSWORD, freshCode($secret, $clock)))->toBe(200)
        ->and($browser->body())->toContain('Keep these recovery codes')
        ->and($browser->body())->toContain('href="/cp/preferences"');
    preg_match_all('#<li><code>([a-z0-9-]{11})</code></li>#', $browser->body(), $new);
    runAfter($after);
    expect($new[1])->toHaveCount(8)
        ->and($mailer->sent[0]->subject)->toBe('Acme Tours: you have new recovery codes')
        ->and($container->get(AuditLog::class)->recentEvents())->toContain('recovery_codes_replaced');

    // An old code no longer signs in; a new one does.
    $browser->get('/cp/');
    expect(signInFrom($browser, $oldCodes[0], $after))->toBe(422);
    $browser->get('/cp/');
    expect(signInFrom($browser, $new[1][0], $after))->toBe(303);
});

it('refuses without the right password or a fresh code from the app', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);

    expect(twoFactorForm($browser, 'codes', 'not the password at all', freshCode($secret, $clock)))->toBe(422)
        ->and($browser->body())->toContain('That isn&#039;t your current password');
    expect(twoFactorForm($browser, 'codes', PASSWORD, '000000'))->toBe(422)
        ->and($browser->body())->toContain('That code is not right');
});

it('sets up two-factor again with a new app: the old one stops working, and other devices are signed out', function (): void {
    [$browser, $container, $clock, $mailer, $after] = alertingPanel();
    [$oldSecret] = enrol($browser, $container, $clock);
    $laptop = $browser->another(Browser::WINDOWS_CHROME);
    $laptop->get('/cp/sign-in');
    $laptop->post('/cp/sign-in', ['email' => 'edward@example.com', 'password' => PASSWORD]);
    $laptop->get('/cp/two-factor');
    $laptop->post('/cp/two-factor', ['code' => freshCode($oldSecret, $clock)]);
    runAfter($after);
    $mailer->sent = [];

    $browser->get('/cp/preferences');
    $sent = $browser->post('/cp/preferences', ['form' => 'two-factor', 'task' => 'app', 'two_factor_password' => PASSWORD, 'code' => freshCode($oldSecret, $clock)]);
    expect($sent->getHeaderLine('Location'))->toBe('/cp/two-factor/change');

    $browser->get('/cp/two-factor/change');
    expect($browser->body())->toContain('Set up two-factor again')->toContain('<svg');
    $newSecret = newAppSecret($browser);
    expect($newSecret)->not->toBe($oldSecret);

    // Until the new app is proved, the old one still works.
    expect($browser->post('/cp/two-factor/change', ['code' => codeFor($oldSecret, $clock)])->getStatusCode())->toBe(422);

    $clock->advance(30);
    $done = $browser->post('/cp/two-factor/change', ['code' => codeFor($newSecret, $clock)]);
    runAfter($after);
    expect($done->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Keep these recovery codes')
        ->and($browser->body())->toContain('href="/cp/preferences?saved=two-factor"')
        ->and($mailer->sent[0]->subject)->toBe('Acme Tours: two-factor was set up again')
        ->and($container->get(AuditLog::class)->recentEvents())->toContain('two_factor_changed')
        ->and($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($laptop->get('/cp/')->getHeaderLine('Location'))->toBe('/cp/sign-in');

    // The old app's codes no longer sign in; the new app's do.
    expect(signInFrom($browser, freshCode($oldSecret, $clock), $after))->toBe(422);
    $browser->get('/cp/');
    expect(signInFrom($browser, freshCode($newSecret, $clock), $after))->toBe(303);
});

it('sends the new-app page back to Preferences without the password and code first, or after ten minutes', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    [$secret] = enrol($browser, $container, $clock);

    expect($browser->get('/cp/two-factor/change')->getHeaderLine('Location'))->toBe('/cp/preferences#two-factor');

    twoFactorForm($browser, 'app', PASSWORD, freshCode($secret, $clock));
    $clock->advance(TwoFactorChangeAction::WINDOW_SECONDS + 1);
    expect($browser->get('/cp/two-factor/change')->getHeaderLine('Location'))->toBe('/cp/preferences#two-factor');
});
