<?php

declare(strict_types=1);

use SlimKiller\Cron\Jobs\UpdateCheckJob;
use SlimKiller\Doctor\Checks\UpdatesCheck;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;
use SlimKiller\Support\Paths;
use SlimKiller\Updates\UpdateCheckFailed;
use SlimKiller\Updates\Updates;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\FrozenClock;

/** A copy of Slim Killer on $installed, with a changelog like the real one. */
function slimKillerCopy(string $installed = '0.8.1'): Paths
{
    $paths = new Paths(temporaryFolder());
    file_put_contents($paths->root('CHANGELOG.md'), "# Changelog\n\nWork not released sits under\n**[Unreleased]** at the top.\n\n## [Unreleased]\n\n## [{$installed}] - 2026-11-24\n\n## [0.1.0] - 2026-11-01\n");

    return $paths;
}

/** GitHub answering the tags list with these names. */
function githubTags(string ...$names): HttpClient
{
    return new FakeHttpClient(fn(string $url): Response => new Response(200, json_encode(array_map(
        fn(string $name): array => ['name' => $name, 'commit' => ['sha' => 'abc']],
        $names,
    ), JSON_THROW_ON_ERROR)));
}

function updates(Paths $paths, HttpClient $http): Updates
{
    return new Updates($http, $paths, FrozenClock::at('2026-11-24 18:00:00'));
}

it('reads the version running from the newest release in CHANGELOG.md, not [Unreleased]', function (): void {
    expect(updates(slimKillerCopy('0.8.1'), githubTags())->installed())->toBe('0.8.1')
        ->and(updates(new Paths(temporaryFolder()), githubTags())->installed())->toBeNull();
});

it('finds the newest release among the tags, in version order, and skips the rest', function (): void {
    $paths = slimKillerCopy('0.8.1');
    $asked = [];
    $http = new FakeHttpClient(function (string $url) use (&$asked): Response {
        $asked[] = $url;

        return githubTags('v0.9.0', 'v0.10.0', 'v1.0.0-beta', 'latest', 'v0.8.1')->get($url);
    });
    $updates = updates($paths, $http);

    expect($updates->latest())->toBeNull()
        ->and($updates->check())->toBe('0.10.0')
        ->and($asked)->toBe(['https://api.github.com/repos/monatemedia/slim-killer/tags?per_page=100'])
        ->and($updates->latest())->toBe('0.10.0')
        ->and($updates->newer()?->latest)->toBe('0.10.0')
        ->and($updates->newer()?->installed)->toBe('0.8.1');
});

it('has nothing to say when this copy is the newest, or newer than GitHub knows', function (string $installed): void {
    $updates = updates(slimKillerCopy($installed), githubTags('v0.8.1', 'v0.8.0'));
    $updates->check();

    expect($updates->newer())->toBeNull();
})->with(['the newest' => '0.8.1', 'not tagged yet' => '0.9.0']);

it('says why GitHub could not be asked, and notes nothing', function (Response $answer, string $why): void {
    $paths = slimKillerCopy();
    $updates = updates($paths, new FakeHttpClient(fn(): Response => $answer));

    expect(fn() => $updates->check())->toThrow(UpdateCheckFailed::class, $why)
        ->and($paths->storage(Updates::FILE))->not->toBeFile();
})->with([
    'no answer' => [new Response(0, '', 'Could not resolve host'), 'no answer from api.github.com: Could not resolve host'],
    'rate limited' => [new Response(403, '{}'), 'api.github.com answered HTTP 403'],
    'no release tags' => [new Response(200, '[{"name":"latest"}]'), 'GitHub lists no release tags'],
]);

it('tells the doctor about a newer release, with the changelog and how to update', function (): void {
    $paths = slimKillerCopy('0.8.1');
    $results = (new UpdatesCheck(updates($paths, githubTags('v0.9.0'))))->check();

    expect(statuses($results))->toBe(['warn'])
        ->and($results[0]->title)->toBe('Slim Killer v0.9.0 is out')
        ->and($results[0]->detail)->toBe("this is v0.8.1. What's new: https://github.com/monatemedia/slim-killer/blob/main/CHANGELOG.md")
        ->and($results[0]->hint)->toBe('To update: git fetch --tags && git checkout v0.9.0 && composer install --no-dev && php hammer migrate && php hammer web:install, and anything else CHANGELOG.md asks under [0.9.0].')
        ->and(updates($paths, githubTags())->latest())->toBe('0.9.0');
});

it('passes the newest, and only informs when GitHub cannot be asked', function (): void {
    $newest = (new UpdatesCheck(updates(slimKillerCopy('0.8.1'), githubTags('v0.8.1'))))->check();
    $unasked = (new UpdatesCheck(updates(slimKillerCopy('0.8.1'), new FakeHttpClient(fn(): Response => new Response(0, '')))))->check();

    expect(statuses($newest))->toBe(['pass'])
        ->and($newest[0]->title)->toBe('Slim Killer v0.8.1')
        ->and(statuses($unasked))->toBe(['info'])
        ->and($unasked[0]->detail)->toStartWith('could not check for a newer one');
});

it('checks once a day from cron, without failing when GitHub is out of reach', function (): void {
    $now = new DateTimeImmutable('2026-11-24 18:00:00');
    $job = new UpdateCheckJob(updates(slimKillerCopy('0.8.1'), githubTags('v0.9.0')));
    $down = new UpdateCheckJob(updates(slimKillerCopy('0.8.1'), new FakeHttpClient(fn(): Response => new Response(0, ''))));

    expect($job->everyMinutes())->toBe(1440)
        ->and($job->run($now))->toBe('Slim Killer v0.9.0 is out; this is v0.8.1.')
        ->and($down->run($now))->toStartWith('Could not ask GitHub for the newest Slim Killer');
});
