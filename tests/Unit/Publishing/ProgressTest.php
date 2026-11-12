<?php

declare(strict_types=1);

use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\Response;
use SlimKiller\Publishing\Progress;
use SlimKiller\Publishing\PublishLog;
use SlimKiller\Publishing\Release;
use SlimKiller\Publishing\Releases;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\SteppingClock;

const SAVED = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

/**
 * A host with a publish log, its releases, and a fake GitHub whose build for SAVED is in
 * $github->run (null: no run yet).
 *
 * @return array{0: Progress, 1: PublishLog, 2: Releases, 3: object, 4: SteppingClock, 5: FakeHttpClient}
 */
function progressHost(): array
{
    $root = temporaryFolder();
    mkdir($root . '/public_html');
    $clock = new SteppingClock('2026-11-11 10:00:00');
    $github = (object) ['run' => null, 'status' => 200];
    $http = new FakeHttpClient(static fn(string $url): Response => new Response($github->status, json_encode([
        'workflow_runs' => $github->run === null ? [] : [$github->run],
    ])));
    $log = new PublishLog($root . '/storage/publishing.json');
    $releases = new Releases($root . '/public_html', $root . '/storage/releases.json');
    $progress = new Progress($log, $releases, $http, new GitHubSettings('owner/site', 'main', 'secret'), $clock, $root . '/storage/cache/build-status.json');

    return [$progress, $log, $releases, $github, $clock, $http];
}

/** Makes a release built from $commit live, as a fetch would. */
function goLive(Releases $releases, string $commit): void
{
    $folder = sys_get_temp_dir() . '/sk-release-' . bin2hex(random_bytes(4));
    mkdir($folder);
    file_put_contents($folder . '/index.html', 'Home');
    $release = new Release(substr(sha1($commit), 0, 12), sha1($commit), $commit, '', '');
    $releases->add($release, $folder);
    $releases->switchTo($release);
}

it('shows nothing before anything was saved', function (): void {
    [$progress] = progressHost();

    expect($progress->report()['stage'])->toBe('none');
});

it('follows a save from GitHub waiting, to building, to built, to live', function (): void {
    [$progress, $log, $releases, $github, $clock] = progressHost();
    $log->saved(SAVED, $clock->now());

    expect($progress->report())->toMatchArray(['stage' => 'building', 'done' => false])
        ->and($progress->report()['message'])->toContain('Waiting for GitHub');

    $github->run = ['status' => 'in_progress', 'conclusion' => null];
    $clock->advance(5);
    expect($progress->report()['message'])->toContain('GitHub is building');

    $github->run = ['status' => 'completed', 'conclusion' => 'success'];
    $clock->advance(5);
    expect($progress->report()['stage'])->toBe('going-live');

    goLive($releases, SAVED);
    expect($progress->report())->toMatchArray(['stage' => 'live', 'done' => true]);
});

it('says the website is unchanged when the build fails', function (): void {
    [$progress, $log, , $github, $clock] = progressHost();
    $log->saved(SAVED, $clock->now());
    $github->run = ['status' => 'completed', 'conclusion' => 'failure'];

    $report = $progress->report();

    expect($report['stage'])->toBe('failed')
        ->and($report['done'])->toBeTrue()
        ->and($report['message'])->toContain('your website is unchanged');
});

it('stops showing a finished publish after ten minutes', function (): void {
    [$progress, $log, $releases, , $clock] = progressHost();
    $log->saved(SAVED, $clock->now());
    goLive($releases, SAVED);
    expect($progress->report()['stage'])->toBe('live');

    $clock->advance(Progress::SHOW_DONE_SECONDS + 1);

    expect($progress->report()['stage'])->toBe('none');
});

it('asks GitHub once every few seconds, however many pages ask', function (): void {
    [$progress, $log, , , $clock, $http] = progressHost();
    $log->saved(SAVED, $clock->now());

    $progress->report();
    $progress->report();
    $progress->report();
    $clock->advance(5);
    $progress->report();

    expect($http->requested)->toHaveCount(2);
});

it('gives no estimate until there are three publishes to go on, then the typical time left', function (): void {
    [$progress, $log, , , $clock] = progressHost();
    foreach ([100, 160, 130] as $index => $seconds) {
        $commit = str_repeat((string) $index, 40);
        $log->saved($commit, $clock->now());
        if ($index === 1) {
            $log->saved(SAVED, $clock->now());
            expect($progress->report()['estimate'])->toBeNull();
        }
        $clock->advance($seconds);
        $log->markLive($commit, $clock->now());
    }

    $log->saved(SAVED, $clock->now());
    expect($progress->report()['estimate'])->toBe('About 2 minutes left.');

    $clock->advance(100);
    expect($progress->report()['estimate'])->toBe('Under a minute left.');

    $clock->advance(60);
    expect($progress->report()['estimate'])->toBe('Any moment now.');
});

it('does not claim to know the build when GitHub will not say', function (): void {
    [$progress, $log, , $github, $clock] = progressHost();
    $log->saved(SAVED, $clock->now());
    $github->status = 403;

    $report = $progress->report();

    expect($report['stage'])->toBe('building')
        ->and($report['message'])->toBe('Saved. Your website is being built.');
});
