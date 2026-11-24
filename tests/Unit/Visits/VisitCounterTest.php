<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\VisitsCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\Jobs\VisitsJob;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\Device;
use SlimKiller\Visits\Event;
use SlimKiller\Visits\EventKind;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\TopBy;
use SlimKiller\Visits\VisitCounter;
use SlimKiller\Visits\VisitLog;
use SlimKiller\Visits\VisitorCodes;
use SlimKiller\Visits\VisitReport;
use Tests\Fakes\FrozenClock;

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6 Mobile/15E148 Safari/604.1';
const WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

/** A visitor's request to /cp/visit.php. Override only what a test is about. */
function beacon(array $data = [], array $server = []): array
{
    return [
        array_replace([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_USER_AGENT' => IPHONE,
            'REMOTE_ADDR' => '198.51.100.7',
        ], $server),
        (string) json_encode(array_replace(['k' => 'page', 'p' => '/'], $data)),
    ];
}

/** @return array{VisitCounter, PDO, VisitLog, VisitorCodes, SiteTime} */
function visitCounter(): array
{
    $pdo = migratedDatabase();
    $log = new VisitLog($pdo);
    $codes = new VisitorCodes($pdo);
    $time = SiteTime::named('Africa/Johannesburg');

    return [new VisitCounter($log, $codes, $time), $pdo, $log, $codes, $time];
}

/** @return list<array<string, mixed>> */
function events(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM visit_events ORDER BY id')->fetchAll();
}

function at(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time);
}

it('counts a page, keeping no internet address and no query', function (): void {
    [$counter, $pdo] = visitCounter();

    $status = $counter->count(...[...beacon(['p' => '/services/?name=Thabo']), at('2026-11-22T08:00:00+02:00')]);

    $rows = events($pdo);
    expect($status)->toBe(204)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['path'])->toBe('/services/')
        ->and($rows[0]['source'])->toBe('direct')
        ->and($rows[0]['device'])->toBe('phone')
        ->and(implode(' ', array_map('strval', $rows[0])))->not->toContain('198.51.100.7');
});

it('counts nothing when the browser asks not to be tracked', function (array $header): void {
    [$counter, $pdo] = visitCounter();

    expect($counter->count(...[...beacon([], $header), at('2026-11-22T08:00:00Z')]))->toBe(204)
        ->and(events($pdo))->toBe([]);
})->with([
    'Do Not Track' => [['HTTP_DNT' => '1']],
    'Global Privacy Control' => [['HTTP_SEC_GPC' => '1']],
]);

it('counts nothing for robots', function (string $userAgent): void {
    [$counter, $pdo] = visitCounter();

    $counter->count(...[...beacon([], ['HTTP_USER_AGENT' => $userAgent]), at('2026-11-22T08:00:00Z')]);

    expect(events($pdo))->toBe([]);
})->with([
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'no description' => '',
    'a script' => 'curl/8.4.0',
]);

it('refuses what other websites send, and anything that is not a beacon', function (array $server, string $body, int $status): void {
    [$counter, $pdo] = visitCounter();
    [$defaults] = beacon();

    expect($counter->count(array_replace($defaults, $server), $body, at('2026-11-22T08:00:00Z')))->toBe($status)
        ->and(events($pdo))->toBe([]);
})->with([
    'another website' => [['HTTP_ORIGIN' => 'https://elsewhere.example'], '{"k":"page","p":"/"}', 403],
    'no origin' => [['HTTP_ORIGIN' => ''], '{"k":"page","p":"/"}', 403],
    'a GET' => [['REQUEST_METHOD' => 'GET'], '', 405],
    'not JSON' => [[], 'hello', 400],
    'too big' => [[], str_repeat('x', VisitCounter::MAX_BYTES + 1), 413],
    'an unknown kind' => [[], '{"k":"scroll","p":"/"}', 204],
    'a full address as the path' => [[], '{"k":"page","p":"//evil.example/"}', 204],
]);

it('works out where a visit came from', function (array $data, string $source, string $medium, string $campaign): void {
    $event = Event::fromBeacon(array_replace(['k' => 'page', 'p' => '/'], $data), IPHONE, 'www.example.com');

    expect([$event?->source, $event?->medium, $event?->campaign])->toBe([$source, $medium, $campaign]);
})->with([
    'campaign tags' => [['s' => 'Newsletter', 'm' => 'email', 'c' => 'november', 'r' => 'https://www.google.com/'], 'newsletter', 'email', 'november'],
    'a Google Ads click' => [['g' => 1], 'google', 'cpc', ''],
    'Google search' => [['r' => 'https://www.google.co.za/'], 'google', 'search', ''],
    'Facebook' => [['r' => 'https://l.facebook.com/l.php?u=x'], 'facebook', 'social', ''],
    'X short links' => [['r' => 'https://t.co/abc'], 'x', 'social', ''],
    'another website' => [['r' => 'https://www.tco.example/page'], 'tco.example', 'referral', ''],
    'its own pages' => [['r' => 'https://example.com/about/'], '', '', ''],
    'nothing said' => [[], '', '', ''],
]);

it('gives a tap the source of the visit it belongs to', function (): void {
    [$counter, $pdo] = visitCounter();
    $now = at('2026-11-22T08:00:00Z');

    $counter->count(...[...beacon(['r' => 'https://www.google.com/']), $now]);
    $counter->count(...[...beacon(['k' => 'tap', 'p' => '/', 'l' => 'WhatsApp: WhatsApp Edward']), $now]);
    $counter->count(...[...beacon(['k' => 'tap'], ['REMOTE_ADDR' => '203.0.113.9']), $now]);

    $rows = events($pdo);
    expect($rows[1]['source'])->toBe('google')
        ->and($rows[1]['label'])->toBe('WhatsApp: WhatsApp Edward')
        ->and($rows[1]['visitor'])->toBe($rows[0]['visitor'])
        ->and($rows[2]['source'])->toBe('direct')
        ->and($rows[2]['visitor'])->not->toBe($rows[0]['visitor']);
});

it('gives the same visitor a new code each day, and forgets the old secret', function (): void {
    [$counter, $pdo, , $codes] = visitCounter();

    $counter->count(...[...beacon(), at('2026-11-22T21:00:00Z')]); // 23:00 in Johannesburg
    $counter->count(...[...beacon(), at('2026-11-22T22:30:00Z')]); // 00:30 the next day there
    $rows = events($pdo);

    expect($rows[0]['day'])->toBe('2026-11-22')
        ->and($rows[1]['day'])->toBe('2026-11-23')
        ->and($rows[1]['visitor'])->not->toBe($rows[0]['visitor'])
        ->and($codes->forgetBefore('2026-11-23'))->toBe(1)
        ->and($pdo->query('SELECT day FROM visitor_secrets')->fetchAll(PDO::FETCH_COLUMN))->toBe(['2026-11-23']);
});

it('tells the kind of device apart', function (string $userAgent, Device $device): void {
    expect(Device::fromUserAgent($userAgent))->toBe($device);
})->with([
    'iPhone' => [IPHONE, Device::Phone],
    'Android phone' => ['Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36', Device::Phone],
    'Android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 Chrome/131.0 Safari/537.36', Device::Tablet],
    'iPad' => ['Mozilla/5.0 (iPad; CPU OS 15_8 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148', Device::Tablet],
    'Windows' => [WINDOWS, Device::Computer],
]);

it('adds up each finished day once, and keeps everything for good', function (): void {
    [$counter, $pdo, $log, $codes, $time] = visitCounter();
    $job = new VisitsJob($pdo, $log, $codes, $time, new AppConfig());

    $counter->count(...[...beacon(['r' => 'https://www.google.com/']), at('2026-11-21T08:00:00Z')]);
    $counter->count(...[...beacon(['p' => '/services/']), at('2026-11-21T08:01:00Z')]);
    $counter->count(...[...beacon(['k' => 'form', 'l' => 'contact']), at('2026-11-21T08:02:00Z')]);
    $counter->count(...[...beacon([], ['HTTP_USER_AGENT' => WINDOWS, 'REMOTE_ADDR' => '203.0.113.9']), at('2026-11-21T09:00:00Z')]);
    $counter->count(...[...beacon(), at('2026-11-22T08:00:00Z')]); // today: not added up yet

    expect($job->run(at('2026-11-22T10:00:00Z')))->toBe('Added up 2026-11-21.')
        ->and($job->run(at('2026-11-22T11:00:00Z')))->toBe('No new days to add up.')
        ->and($log->summary('2026-11-21', '2026-11-21'))->toBe(['visitors' => 2, 'pages' => 3, 'taps' => 0, 'forms' => 1])
        ->and($log->top(null, TopBy::Source, '2026-11-21', '2026-11-21'))->toBe([
            ['name' => 'direct', 'events' => 1],
            ['name' => 'google / search', 'events' => 1],
        ])
        ->and($log->top(EventKind::Form, TopBy::Label, '2026-11-21', '2026-11-21'))->toBe([['name' => 'contact', 'events' => 1]]);

    // Years on, nothing has been deleted.
    expect($job->run(at('2036-11-22T10:00:00Z')))->toBe('Added up 2026-11-22.')
        ->and(events($pdo))->toHaveCount(5);
});

it('deletes single events after the months a site sets, and keeps the totals', function (): void {
    [$counter, $pdo, $log, $codes, $time] = visitCounter();
    $job = new VisitsJob($pdo, $log, $codes, $time, new AppConfig(visitEventsMonths: 25));

    $counter->count(...[...beacon(), at('2026-11-21T08:00:00Z')]);
    $counter->count(...[...beacon(['p' => '/services/']), at('2026-11-22T08:00:00Z')]);
    $job->run(at('2026-11-23T10:00:00Z'));

    expect($job->run(at('2028-12-22T10:00:00Z')))->toBe('No new days to add up. Deleted 1 event older than 25 months.')
        ->and(events($pdo))->toHaveCount(1)
        ->and($log->summary('2026-11-21', '2026-11-22'))->toBe(['visitors' => 2, 'pages' => 2, 'taps' => 0, 'forms' => 0]);
});

it('reads the months to keep from .env, and keeps everything when it is empty or not a number', function (string $value, ?int $months): void {
    expect(AppConfig::fromEnvironment(['VISIT_EVENTS_MONTHS' => $value])->visitEventsMonths)->toBe($months);
})->with([
    'France' => ['25', 25],
    'empty' => ['', null],
    'not a number' => ['two years', null],
    'zero' => ['0', null],
]);

it('says when the visit counter is not set up yet', function (): void {
    $pdo = SlimKiller\Database\Database::open(':memory:');
    $time = SiteTime::named('UTC');
    $job = new VisitsJob($pdo, new VisitLog($pdo), new VisitorCodes($pdo), $time, new AppConfig());

    expect($job->run(at('2026-11-22T10:00:00Z')))->toBe('The visit counter is not set up yet. Run php hammer migrate.');
});

it('shows the last days in hammer visits', function (): void {
    [$counter, $pdo, $log, $codes, $time] = visitCounter();
    $counter->count(...[...beacon(['r' => 'https://www.google.com/']), at('2026-11-21T08:00:00Z')]);
    $counter->count(...[...beacon(['k' => 'tap', 'l' => 'WhatsApp: WhatsApp Edward']), at('2026-11-21T08:01:00Z')]);
    (new VisitsJob($pdo, $log, $codes, $time, new AppConfig()))->run(at('2026-11-22T10:00:00Z'));

    $stream = fopen('php://memory', 'w+');
    $command = new VisitsCommand($log, new VisitReport($log), $time, FrozenClock::at('2026-11-22T10:00:00Z'));
    $code = $command->run(Input::fromArgv(['hammer', 'visits']), new Output($stream, false));
    rewind($stream);
    $printed = (string) stream_get_contents($stream);

    expect($code)->toBe(0)
        ->and($printed)->toContain('Visits from 2026-11-15 to 2026-11-21')
        ->and($printed)->toMatch('/Visitors \(day by day\)\s+1/')
        ->and($printed)->toContain('google / search')
        ->and($printed)->toContain('WhatsApp: WhatsApp Edward');
});

it('uses UTC for an unknown time zone', function (): void {
    expect(SiteTime::named('Mars/Olympus')->zone->getName())->toBe('UTC')
        ->and(SiteTime::named('Africa/Johannesburg')->day(at('2026-11-22T22:30:00Z')))->toBe('2026-11-23');
});

it('counts a page not found apart from the pages, with where its link was', function (): void {
    [$counter, $pdo, $log, $codes, $time] = visitCounter();
    $missing = fn(string $path, string $from): array => beacon(['k' => 'missing', 'p' => $path, 'r' => $from]);

    $counter->count(...[...beacon(['p' => '/services/']), at('2026-11-21T08:00:00Z')]);
    $counter->count(...[...$missing('/old-prices/', 'https://www.example.com/services/?ref=menu'), at('2026-11-21T08:01:00Z')]);
    $counter->count(...[...$missing('/old-prices/', 'https://example.com/services/'), at('2026-11-21T08:02:00Z')]);
    $counter->count(...[...$missing('/wp-login.php', 'https://www.google.co.za/'), at('2026-11-21T08:03:00Z')]);
    $counter->count(...[...$missing('/typed-wrong/', ''), at('2026-11-21T08:04:00Z')]);
    (new VisitsJob($pdo, $log, $codes, $time, new AppConfig()))->run(at('2026-11-22T10:00:00Z'));

    $lists = array_column((new VisitReport($log))->lists('2026-11-21', '2026-11-21'), 'rows', 'heading');

    expect($lists['Pages'])->toBe([['name' => '/services/', 'events' => 1]])
        ->and($lists['Pages not found'])->toBe([
            ['name' => '/old-prices/ (from /services/)', 'events' => 2],
            ['name' => '/typed-wrong/', 'events' => 1],
            ['name' => '/wp-login.php (from google.co.za)', 'events' => 1],
        ])
        ->and(array_keys($lists))->toBe(['Where visitors came from', 'Pages', 'Pages not found', 'Devices'])
        ->and($log->summary('2026-11-21', '2026-11-21')['pages'])->toBe(1);
});

it('keeps the events counted before pages not found, and accepts the new kind', function (): void {
    $folder = temporaryFolder();
    $all = glob(dirname(__DIR__, 3) . '/database/migrations/*.sql') ?: [];
    foreach ($all as $file) {
        if (! str_starts_with(basename($file), '0005')) {
            copy($file, $folder . '/' . basename($file));
        }
    }
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    (new SlimKiller\Database\Migrator($pdo, $folder))->migrate();
    $pdo->exec("INSERT INTO visit_events (at, day, visitor, kind, path, source, device) VALUES (1, '2026-11-20', 'v1', 'page', '/', 'direct', 'phone')");

    copy(dirname(__DIR__, 3) . '/database/migrations/0005_pages_not_found.sql', $folder . '/0005_pages_not_found.sql');
    $ran = (new SlimKiller\Database\Migrator($pdo, $folder))->migrate();
    $pdo->exec("INSERT INTO visit_events (at, day, visitor, kind, path, source, device) VALUES (2, '2026-11-20', 'v1', 'missing', '/old/', 'direct', 'phone')");

    expect($ran)->toBe(['0005_pages_not_found'])
        ->and($pdo->query('SELECT kind || path FROM visit_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))->toBe(['page/', 'missing/old/'])
        ->and($pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'visit_events'")->fetchAll(PDO::FETCH_COLUMN))->toBe(['visit_events_by_visitor']);
});
