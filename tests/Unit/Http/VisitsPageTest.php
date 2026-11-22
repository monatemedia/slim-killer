<?php

declare(strict_types=1);

use SlimKiller\Cron\Jobs\VisitsJob;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitCounter;

/** A signed-in panel. The panel's clock says 2026-11-09 12:00. */
function visitsPanel(): array
{
    [$browser, $container, $clock] = installedPanel([SiteTime::class => SiteTime::named('UTC')]);
    enrol($browser, $container, $clock);

    return [$browser, $container, $clock];
}

/** Counts one event, as visits.js would send it. */
function countVisit(Psr\Container\ContainerInterface $container, string $at, array $data = [], string $address = '198.51.100.7'): void
{
    $container->get(VisitCounter::class)->count(
        [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'client.test',
            'HTTP_ORIGIN' => 'https://client.test',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) Mobile/15E148 Safari/604.1',
            'REMOTE_ADDR' => $address,
        ],
        (string) json_encode(['k' => 'page', 'p' => '/', ...$data]),
        new DateTimeImmutable($at),
    );
}

it('keeps the visits page for signed-in people', function (): void {
    [$browser] = installedPanel();

    expect($browser->get('/cp/visits')->getHeaderLine('Location'))->toBe('/cp/sign-in');
});

it('says when nothing has been counted yet', function (): void {
    [$browser] = visitsPanel();

    $browser->get('/cp/visits');

    expect($browser->body())->toContain('Nothing counted yet')
        ->and($browser->body())->not->toContain('Visitors, day by day');
});

it('shows the last 30 days next to the 30 before, day by day, where visitors came from, and today so far', function (): void {
    [$browser, $container, $clock] = visitsPanel();
    countVisit($container, '2026-10-01T12:00:00Z');
    countVisit($container, '2026-11-01T12:00:00Z', ['r' => 'https://www.google.com/']);
    countVisit($container, '2026-11-01T12:01:00Z', ['k' => 'tap', 'l' => 'WhatsApp: WhatsApp us']);
    countVisit($container, '2026-11-02T12:00:00Z', ['p' => '/services/'], '203.0.113.9');
    countVisit($container, $clock->now()->format(DATE_ATOM));
    $container->get(VisitsJob::class)->run($clock->now());

    $browser->get('/cp/visits');
    $page = $browser->body();

    expect($page)->toContain('10 October 2026 to 8 November 2026')
        ->and($page)->toContain('href="/cp/visits?days=30" aria-current="page"')
        ->and($page)->toMatch('#Visitors</span>\s*<span class="stat-value">2</span>#')
        ->and($page)->toContain('+100% on the 30 days before')
        ->and($page)->toContain('Nothing to compare with yet')
        ->and($page)->toContain('Most in a day: 1')
        ->and($page)->toContain('google / search')
        ->and($page)->toContain('WhatsApp: WhatsApp us')
        ->and($page)->toContain('/services/')
        ->and($page)->toMatch('#<strong>1</strong> visitor ·#');
});

it('switches between 7, 30 and 90 days and 12 months, and ignores anything else', function (string $asked, string $current, string $from): void {
    [$browser, $container, $clock] = visitsPanel();
    countVisit($container, '2026-11-02T12:00:00Z');
    $container->get(VisitsJob::class)->run($clock->now());

    $browser->get('/cp/visits?days=' . $asked);

    expect($browser->body())->toContain('href="/cp/visits?days=' . $current . '" aria-current="page"')
        ->and($browser->body())->toContain($from . ' to 8 November 2026');
})->with([
    '7 days' => ['7', '7', '2 November 2026'],
    '12 months' => ['365', '365', '9 November 2025'],
    'not a choice' => ['5', '30', '10 October 2026'],
    'not a number' => ['all', '30', '10 October 2026'],
]);

it('links to the visits page from the dashboard', function (): void {
    [$browser] = visitsPanel();

    $browser->get('/cp/');

    expect($browser->body())->toContain('href="/cp/visits"');
});
