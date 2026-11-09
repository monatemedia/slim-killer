<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\Invites;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Database\Migrator;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;
use Tests\Fakes\Browser;
use Tests\Fakes\SteppingClock;

const PASSWORD = 'correct horse battery staple';

/** @return array{0: Browser, 1: ContainerInterface, 2: SteppingClock} */
function installedPanel(): array
{
    $clock = new SteppingClock('2026-11-09 12:00:00');
    $builder = new ContainerBuilder();
    $builder->addDefinitions(dirname(__DIR__, 3) . '/config/container.php');
    $builder->addDefinitions([
        Paths::class => new Paths(temporaryFolder()),
        Clock::class => $clock,
        Environment::class => environment(['sapi' => 'litespeed'], 'Website'),
    ]);
    $container = $builder->build();
    $container->get(Migrator::class)->migrate();

    return [new Browser(ControlPanel::create($container, showErrorDetails: true, logErrors: false)), $container, $clock];
}

function welcomeLink(ContainerInterface $container, string $email = 'edward@example.com'): string
{
    $user = $container->get(UserRepository::class)->create(EmailAddress::from($email), 'Edward', Role::Admin);

    return Invites::pathFor($container->get(Invites::class)->issue($user->id));
}

/** Takes a new user from the welcome link to signed in. Returns the secret and recovery codes. */
function enrol(Browser $browser, ContainerInterface $container, SteppingClock $clock): array
{
    $browser->get(welcomeLink($container));
    $browser->get('/cp/welcome');
    $browser->post('/cp/welcome', ['password' => PASSWORD, 'password_confirmation' => PASSWORD]);
    $browser->get('/cp/two-factor/setup');
    preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $browser->body(), $match);
    $secret = str_replace(' ', '', $match[1] ?? '');
    $browser->post('/cp/two-factor/setup', ['code' => (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp())]);
    preg_match_all('#<li><code>([a-z0-9-]{11})</code></li>#', $browser->body(), $codes);
    $browser->get('/cp/');

    return [$secret, $codes[1]];
}

it('takes a new user from the welcome link, through a password and two-factor, to the dashboard', function (): void {
    [$browser, $container, $clock] = installedPanel();

    $first = $browser->get(welcomeLink($container));
    expect($first->getStatusCode())->toBe(303)
        ->and($first->getHeaderLine('Location'))->toBe('/cp/welcome');

    expect($browser->get('/cp/welcome')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Welcome, Edward');

    $short = $browser->post('/cp/welcome', ['password' => 'short', 'password_confirmation' => 'short']);
    expect($short->getStatusCode())->toBe(422)->and($browser->body())->toContain('at least 12');

    $saved = $browser->post('/cp/welcome', ['password' => PASSWORD, 'password_confirmation' => PASSWORD]);
    expect($saved->getHeaderLine('Location'))->toBe('/cp/two-factor/setup');

    $setup = $browser->get('/cp/two-factor/setup');
    preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $browser->body(), $match);
    $secret = str_replace(' ', '', $match[1] ?? '');
    expect($setup->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('<svg')
        ->and($browser->body())->not->toContain('style=')
        ->and(strlen($secret))->toBe(32);

    expect($browser->post('/cp/two-factor/setup', ['code' => '000000'])->getStatusCode())->toBe(422);

    $done = $browser->post('/cp/two-factor/setup', ['code' => (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp())]);
    expect($done->getStatusCode())->toBe(200)
        ->and(substr_count($browser->body(), '<li><code>'))->toBe(8);

    expect($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Hello, Edward.')
        ->and($browser->body())->toContain('8 recovery codes left');

    expect($container->get(AuditLog::class)->recentEvents())->toContain('two_factor_enabled')->toContain('login')->toContain('password_set');
});

it('signs in again with the password and a new code, and refuses a code that was already used', function (): void {
    [$browser, $container, $clock] = installedPanel();
    [$secret] = enrol($browser, $container, $clock);
    $usedCode = (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp());

    expect($browser->post('/cp/logout', [])->getHeaderLine('Location'))->toBe('/cp/login')
        ->and($browser->hasSession())->toBeFalse()
        ->and($browser->get('/cp/')->getHeaderLine('Location'))->toBe('/cp/login');

    $browser->get('/cp/login');
    $wrong = $browser->post('/cp/login', ['email' => 'edward@example.com', 'password' => 'not the password at all']);
    expect($wrong->getStatusCode())->toBe(422)->and($browser->body())->toContain('do not match');

    $right = $browser->post('/cp/login', ['email' => 'EDWARD@example.com', 'password' => PASSWORD]);
    expect($right->getHeaderLine('Location'))->toBe('/cp/two-factor')
        ->and($browser->get('/cp/')->getHeaderLine('Location'))->toBe('/cp/login');

    $browser->get('/cp/two-factor');
    expect($browser->post('/cp/two-factor', ['code' => $usedCode])->getStatusCode())->toBe(422);

    $clock->advance(30);
    $fresh = $browser->post('/cp/two-factor', ['code' => (new Totp($clock))->codeAt($secret, $clock->now()->getTimestamp())]);
    expect($fresh->getHeaderLine('Location'))->toBe('/cp/')
        ->and($browser->get('/cp/')->getStatusCode())->toBe(200);
});

it('accepts a recovery code instead of the phone, once', function (): void {
    [$browser, $container, $clock] = installedPanel();
    [, $codes] = enrol($browser, $container, $clock);

    foreach ([303, 422] as $expected) {
        $browser->post('/cp/logout', []);
        $browser->get('/cp/login');
        $browser->post('/cp/login', ['email' => 'edward@example.com', 'password' => PASSWORD]);
        $browser->get('/cp/two-factor');

        expect($browser->post('/cp/two-factor', ['code' => $codes[0]])->getStatusCode())->toBe($expected);
        $browser->get('/cp/');
    }
});

it('stops guessing: after five wrong passwords even the right one waits', function (): void {
    [$browser, $container, $clock] = installedPanel();
    enrol($browser, $container, $clock);
    $browser->post('/cp/logout', []);
    $browser->get('/cp/login');

    foreach (range(1, 5) as $_) {
        $browser->post('/cp/login', ['email' => 'edward@example.com', 'password' => 'wrong wrong wrong']);
    }
    $blocked = $browser->post('/cp/login', ['email' => 'edward@example.com', 'password' => PASSWORD]);

    expect($blocked->getStatusCode())->toBe(429)
        ->and($browser->body())->toContain('Try again in 15 minutes');
});

it('refuses a form without its token', function (): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);
    $browser->get('/cp/login');

    $response = $browser->post('/cp/login', ['_csrf' => 'forged', 'email' => 'edward@example.com', 'password' => PASSWORD]);

    expect($response->getStatusCode())->toBe(403)
        ->and($browser->body())->toContain('This form has expired');
});

it('turns away an expired or unknown welcome link', function (): void {
    [$browser, $container, $clock] = installedPanel();
    $link = welcomeLink($container);
    $clock->advance(72 * 3600);

    expect($browser->get($link)->getStatusCode())->toBe(403)
        ->and($browser->body())->toContain('welcome link has expired')
        ->and($browser->get('/cp/welcome')->getStatusCode())->toBe(403);
});

it('keeps visitors who are not signed in out of the two-factor pages', function (string $path): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);

    expect($browser->get($path)->getHeaderLine('Location'))->toBe('/cp/login');
})->with(['/cp/two-factor', '/cp/two-factor/setup']);

it('gives its own messages instead of the browser\'s, and lets people see what they typed', function (): void {
    [$browser, $container] = installedPanel();
    $browser->get(welcomeLink($container));
    $browser->get('/cp/welcome');

    expect($browser->body())->toContain('novalidate')
        ->and($browser->body())->not->toContain('minlength')
        ->and($browser->body())->toContain('data-reveal="password"')
        ->and($browser->body())->toContain('data-reveal="password_confirmation"');

    $browser->post('/cp/welcome', ['password' => '', 'password_confirmation' => '']);
    expect($browser->body())->toContain('at least 12');
});

it('asks for both fields on the sign-in page before checking anything', function (): void {
    [$browser, $container] = installedPanel();
    welcomeLink($container);
    $browser->get('/cp/login');

    expect($browser->body())->toContain('data-reveal="password"');
    expect($browser->post('/cp/login', ['email' => 'edward@example.com', 'password' => ''])->getStatusCode())->toBe(422)
        ->and($browser->body())->toContain('Type your email address and your password');
});

it('offers the recovery codes as a download, and says how to use them', function (): void {
    [$browser, $container, $clock] = installedPanel();
    $browser->get(welcomeLink($container));
    $browser->get('/cp/welcome');
    $browser->post('/cp/welcome', ['password' => PASSWORD, 'password_confirmation' => PASSWORD]);
    $browser->get('/cp/two-factor/setup');
    preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $browser->body(), $match);
    $browser->post('/cp/two-factor/setup', ['code' => (new Totp($clock))->codeAt(str_replace(' ', '', $match[1] ?? ''), $clock->now()->getTimestamp())]);

    expect($browser->body())->toContain('data-download-codes data-account="edward@example.com" data-site="client.test" data-site-name="Slim Killer"')
        ->and($browser->body())->toContain('How to use one');
});
