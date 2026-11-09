<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\CronHeartbeatCommand;
use SlimKiller\Doctor\Checks\CronCheck;
use SlimKiller\Doctor\Checks\GitHubReachableCheck;
use SlimKiller\Doctor\Checks\StorageCheck;
use SlimKiller\Http\Response;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\FrozenClock;

function writeHeartbeat(Paths $paths, string $at, string $version = '8.4.27'): void
{
    @mkdir($paths->storage(), 0755, true);
    file_put_contents($paths->storage(CronHeartbeatCommand::FILE), json_encode(['at' => $at, 'php_version' => $version]));
}

// --- Storage -----------------------------------------------------------------------

it('passes a writable storage folder and leaves nothing behind', function (): void {
    $paths = new Paths(temporaryFolder());

    expect(statuses((new StorageCheck($paths))->check()))->toBe(['pass'])
        ->and(glob($paths->storage('.doctor-*')))->toBe([]);
});

// --- GitHub ------------------------------------------------------------------------

it('passes when GitHub answers', function (): void {
    $http = new FakeHttpClient(fn(string $url): Response => str_contains($url, 'api.github.com')
        ? new Response(200, 'Keep it logically awesome.')
        : new Response(404, 'Not Found'));

    expect(statuses((new GitHubReachableCheck($http))->check()))->toBe(['pass', 'pass']);
});

it('warns when the API answers but not with success, as a filtering proxy would', function (): void {
    $http = FakeHttpClient::always(new Response(403, 'Forbidden'));

    expect(statuses((new GitHubReachableCheck($http))->check()))->toBe(['warn', 'pass']);
});

it('fails, naming the host, when the connection is blocked', function (): void {
    $http = FakeHttpClient::always(new Response(0, '', 'Could not resolve host'));
    $results = (new GitHubReachableCheck($http))->check();

    expect(statuses($results))->toBe(['fail', 'fail'])
        ->and($results[0]->hint)->toContain('api.github.com');
});

// --- Cron --------------------------------------------------------------------------

it('gives the exact cron line before cron has ever run', function (): void {
    $paths = new Paths(temporaryFolder());
    [$result] = (new CronCheck($paths, FrozenClock::at('2026-11-09 01:00:00'), environment()))->check();

    expect($result->status->value)->toBe('warn')
        ->and($result->hint)->toBe('/opt/alt/php84/usr/bin/php ' . $paths->root('hammer') . ' cron:heartbeat');
});

it('passes when cron ran within the last few minutes', function (): void {
    $paths = new Paths(temporaryFolder());
    writeHeartbeat($paths, '2026-11-09T01:00:00+00:00');

    [$result] = (new CronCheck($paths, FrozenClock::at('2026-11-09T01:00:42+00:00'), environment()))->check();

    expect($result->status->value)->toBe('pass')->and($result->detail)->toContain('42 seconds');
});

it('warns when cron has gone quiet', function (): void {
    $paths = new Paths(temporaryFolder());
    writeHeartbeat($paths, '2026-11-09T01:00:00+00:00');

    [$result] = (new CronCheck($paths, FrozenClock::at('2026-11-09T03:00:00+00:00'), environment()))->check();

    expect($result->status->value)->toBe('warn')->and($result->detail)->toContain('2 hours');
});

it('fails when cron runs an old PHP', function (): void {
    $paths = new Paths(temporaryFolder());
    writeHeartbeat($paths, '2026-11-09T01:00:00+00:00', '8.3.36');

    [$result] = (new CronCheck($paths, FrozenClock::at('2026-11-09T01:00:30+00:00'), environment()))->check();

    expect($result->status->value)->toBe('fail')->and($result->detail)->toContain('8.3.36');
});
