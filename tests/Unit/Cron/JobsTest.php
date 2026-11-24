<?php

declare(strict_types=1);

use SlimKiller\Content\ContentLocation;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Cron\Jobs\ContactFormJob;
use SlimKiller\Cron\Jobs\DeployFetchJob;
use SlimKiller\Cron\Jobs\HeartbeatJob;
use SlimKiller\Doctor\Checks\CronCheck;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\Response;
use SlimKiller\Publishing\Fetcher;
use SlimKiller\Publishing\Release;
use SlimKiller\Publishing\Releases;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\FrozenClock;

const CRON_NOW = '2026-11-21T12:00:00+00:00';

/** A web root with one live release holding $files. */
function liveRelease(array $files = []): Releases
{
    $web = temporaryFolder();
    $releases = new Releases($web, temporaryFolder() . '/releases.json');
    $folder = temporaryFolder();
    file_put_contents($folder . '/index.html', 'Home');
    foreach ($files as $name => $source) {
        copy($source, $folder . '/' . $name);
    }
    $release = new Release('abc123def456', str_repeat('a', 40), str_repeat('b', 40), '', '');
    $releases->add($release, $folder);
    $releases->switchTo($release);

    return $releases;
}

function deployFetchJob(GitHubSettings $settings, string $webRoot, FakeHttpClient $http): DeployFetchJob
{
    $paths = new Paths(temporaryFolder());
    $releases = new Releases($webRoot, $paths->storage('releases.json'));
    $location = new ContentLocation($paths->storage('site/content'), $paths->storage('site/schemas'));

    return new DeployFetchJob(new Fetcher($http, $settings, $releases, $location, $paths, FrozenClock::at(CRON_NOW)), $settings, $releases);
}

// --- Heartbeat ---------------------------------------------------------------------

it('notes that cron:run ran, which the doctor accepts', function (): void {
    $paths = new Paths(temporaryFolder());
    $now = new DateTimeImmutable(CRON_NOW);

    $message = (new HeartbeatJob(new Heartbeat($paths)))->run($now);
    [$result] = (new CronCheck($paths, FrozenClock::at('2026-11-21T12:00:40+00:00'), environment(), stagingConfig()))->check();

    expect($message)->toBe('Noted that cron ran.')
        ->and($result->status->value)->toBe('pass');
});

// --- The safety net for a missed build ---------------------------------------------

it('asks GitHub nothing before publishing is set up, and does not call that a failure', function (): void {
    $http = FakeHttpClient::always(new Response(500, ''));
    $job = deployFetchJob(new GitHubSettings('', 'main', ''), '', $http);

    expect($job->run(new DateTimeImmutable(CRON_NOW)))->toBe('Publishing is not set up yet, so there is nothing to fetch.')
        ->and($http->requested)->toBe([]);
});

it('fails, saying why, when the fetch fails', function (): void {
    $http = FakeHttpClient::always(new Response(404, '{"message":"Not Found"}'));
    $job = deployFetchJob(new GitHubSettings('owner/site', 'main', 'secret'), temporaryFolder(), $http);

    expect(fn() => $job->run(new DateTimeImmutable(CRON_NOW)))->toThrow(JobFailed::class, 'GitHub has no build yet');
});

// --- The contact form's held messages ----------------------------------------------

it('has nothing to do on a website without a contact form', function (): void {
    $job = new ContactFormJob(liveRelease());

    expect($job->run(new DateTimeImmutable(CRON_NOW)))->toBe('This website has no contact form.');
});

it('has nothing to do before the first release', function (): void {
    $job = new ContactFormJob(new Releases(temporaryFolder(), temporaryFolder() . '/releases.json'));

    expect($job->run(new DateTimeImmutable(CRON_NOW)))->toBe('This website has no contact form.');
});

it('sends what is due through the live release\'s contact.php, with the time cron gives it', function (): void {
    $GLOBALS['contactFormSentAt'] = [];
    $GLOBALS['contactFormDue'] = 3;
    $job = new ContactFormJob(liveRelease(['contact.php' => dirname(__DIR__, 2) . '/Fixtures/contact-form/contact.php']));

    $message = $job->run(new DateTimeImmutable(CRON_NOW));

    expect($message)->toBe('Sent 3 held messages.')
        ->and($GLOBALS['contactFormSentAt'])->toBe([(new DateTimeImmutable(CRON_NOW))->getTimestamp()]);
});

it('fails, pointing at the error log, when the contact form is not set up', function (): void {
    $GLOBALS['contactFormSetUp'] = false;
    $job = new ContactFormJob(liveRelease(['contact.php' => dirname(__DIR__, 2) . '/Fixtures/contact-form/contact.php']));

    try {
        expect(fn() => $job->run(new DateTimeImmutable(CRON_NOW)))->toThrow(JobFailed::class, 'contact-config.php is missing or wrong');
    } finally {
        unset($GLOBALS['contactFormSetUp']);
    }
});
