<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\Environment\Environment;
use SlimKiller\Doctor\Report;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\Section;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;
use Tests\Fakes\FrozenClock;

function controlPanel(Paths $paths): App
{
    $builder = new ContainerBuilder();
    $builder->addDefinitions(dirname(__DIR__, 3) . '/config/container.php');
    $builder->addDefinitions([
        Paths::class => $paths,
        Clock::class => FrozenClock::at('2026-11-09 12:00:00'),
        Environment::class => environment(['sapi' => 'litespeed'], 'Website'),
    ]);

    return ControlPanel::create($builder->build(), showErrorDetails: true, logErrors: false);
}

function getPage(App $app, string $uri): ResponseInterface
{
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
    $request = (new ServerRequestFactory())->createServerRequest('GET', $uri)->withQueryParams($query);

    return $app->handle($request);
}

function issueSetupLink(Paths $paths): string
{
    return (new SetupLink($paths, FrozenClock::at('2026-11-09 12:00:00')))->issue();
}

it('shows only that the panel is being set up, without the link', function (): void {
    $response = getPage(controlPanel(new Paths(temporaryFolder())), 'https://client.test/cp/');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getBody())->toContain('being set up')->not->toContain('passed');
});

it('refuses the setup page without a valid link', function (string $query): void {
    $paths = new Paths(temporaryFolder());
    issueSetupLink($paths);

    $response = getPage(controlPanel($paths), 'https://client.test/cp/setup' . $query);

    expect($response->getStatusCode())->toBe(403)
        ->and((string) $response->getBody())->toContain('expired')->not->toContain("It's alive");
})->with(['no token' => [''], 'wrong token' => ['?token=0123456789abcdef0123456789abcdef']]);

it('shows the website checked live and the doctor\'s last report, with the link', function (): void {
    $paths = new Paths(temporaryFolder());
    (new ReportStore($paths, FrozenClock::at('2026-11-09 11:48:00')))->save(new Report([
        new Section('Server', [CheckResult::warn('Cron has not run yet', 'add this cron job', '/opt/alt/php84/usr/bin/php hammer cron:heartbeat')]),
        new Section(Doctor::WEBSITE_NOT_CHECKED, [CheckResult::warn('Website not checked')]),
    ]));

    $response = getPage(controlPanel($paths), 'https://client.test/cp/setup?token=' . issueSetupLink($paths));
    $body = (string) $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain("It's alive")
        ->toContain('client.test')
        ->toContain('Website (PHP 8.4.27, litespeed)')
        ->toContain('Cron has not run yet')
        ->toContain('12 minutes ago')
        ->not->toContain('Website not checked');
});

it('says what is still unchecked when the doctor has not saved a report', function (): void {
    $paths = new Paths(temporaryFolder());

    $body = (string) getPage(controlPanel($paths), 'https://client.test/cp/setup?token=' . issueSetupLink($paths))->getBody();

    expect($body)->toContain('Not checked yet');
});

it('sends its security headers on every response, errors included', function (string $path): void {
    $response = getPage(controlPanel(new Paths(temporaryFolder())), 'https://client.test' . $path);

    expect($response->getHeaderLine('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->getHeaderLine('Content-Security-Policy'))->toContain("script-src 'self'")
        ->and($response->getHeaderLine('Content-Security-Policy'))->not->toContain('unsafe-inline')
        ->and($response->getHeaderLine('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->getHeaderLine('Cache-Control'))->toBe('no-store');
})->with(['/cp/', '/cp/setup', '/cp/no-such-page']);

it('moves /cp to /cp/', function (): void {
    $response = getPage(controlPanel(new Paths(temporaryFolder())), 'https://client.test/cp');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaderLine('Location'))->toBe('/cp/');
});
