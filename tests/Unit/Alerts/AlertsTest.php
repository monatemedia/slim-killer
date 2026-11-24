<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\AlertsTestCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\CronRunner;
use SlimKiller\Cron\Heartbeat;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Doctor\Checks\AlertsCheck;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeJob;
use Tests\Fakes\FrozenClock;
use Tests\Fakes\SteppingClock;

/** 02:15 on 24 November in Johannesburg. */
const ALERT_NOW = '2026-11-24T00:15:00+00:00';

function alertAt(int $minutes): DateTimeImmutable
{
    return (new DateTimeImmutable(ALERT_NOW))->modify("+{$minutes} minutes");
}

/** @param list<SlimKiller\Mail\Letter> $letters */
function subjects(array $letters): array
{
    return array_map(static fn($letter): string => $letter->subject, $letters);
}

// --- Cron jobs -------------------------------------------------------------------------

it('emails once when a cron job fails twice in a row, and once when it works again', function (): void {
    $paths = new Paths(temporaryFolder());
    [$alerts, $mailer] = testAlerts($paths);

    $alerts->jobFailed('backup', 'Google Drive did not take the upload (Backend Error).', alertAt(0));
    $afterOne = count($mailer->sent);
    $alerts->jobFailed('backup', 'Google Drive did not take the upload (Backend Error).', alertAt(60));
    $alerts->jobFailed('backup', 'Google Drive did not take the upload (Backend Error).', alertAt(120));
    $alerts->jobDone('backup', alertAt(180));
    $alerts->jobDone('backup', alertAt(240));

    expect($afterOne)->toBe(0)
        ->and(subjects($mailer->sent))->toBe([
            'Acme Tours: the daily backup has stopped working',
            'Acme Tours: the daily backup works again',
        ])
        ->and($mailer->sent[0]->to)->toBe('owner@example.com')
        ->and($mailer->sent[0]->text)->toContain('Since: 24 November 2026, 02:15')
        ->and($mailer->sent[0]->text)->toContain('Backend Error')
        ->and($mailer->sent[0]->text)->toContain('Run php hammer backup on the server')
        ->and($mailer->sent[1]->text)->toContain('It stopped on 24 November 2026, 02:15 and worked again on 24 November 2026, 05:15.')
        ->and($alerts->open())->toBe([]);
});

it('sends nothing for one failure followed by success', function (): void {
    [$alerts, $mailer] = testAlerts(new Paths(temporaryFolder()));

    $alerts->jobFailed('deploy-fetch', 'GitHub took too long.', alertAt(0));
    $alerts->jobDone('deploy-fetch', alertAt(5));

    expect($mailer->sent)->toBe([])->and($alerts->open())->toBe([]);
});

it('hears about cron jobs from cron:run itself', function (): void {
    $paths = new Paths(temporaryFolder());
    [$alerts, $mailer] = testAlerts($paths);
    $failing = new FakeJob('visits', work: static function (): string {
        throw new JobFailed('The database is locked.');
    });
    $clock = new SteppingClock(ALERT_NOW);
    $runner = new CronRunner([$failing], $paths, $clock, $alerts);

    $runner->run();
    $clock->advance(60);
    $runner->run();

    expect(subjects($mailer->sent))->toBe(['Acme Tours: adding up the day\'s visits has stopped working'])
        ->and($mailer->sent[0]->text)->toContain('The database is locked.');
});

// --- Cron going quiet -----------------------------------------------------------------------

it('emails once when cron has been quiet for 15 minutes, and once when it runs again', function (): void {
    $paths = new Paths(temporaryFolder());
    [$alerts, $mailer] = testAlerts($paths);
    $heartbeat = new Heartbeat($paths);
    $heartbeat->record(alertAt(0), 'cron:run');

    $alerts->checkCron($heartbeat, alertAt(10));
    $afterTen = count($mailer->sent);
    $alerts->checkCron($heartbeat, alertAt(20));
    $alerts->checkCron($heartbeat, alertAt(30));
    $alerts->cronRan(alertAt(40));

    expect($afterTen)->toBe(0)
        ->and(subjects($mailer->sent))->toBe([
            'Acme Tours: cron (the jobs that run on a timer) has stopped working',
            'Acme Tours: cron (the jobs that run on a timer) works again',
        ])
        ->and($mailer->sent[0]->text)->toContain('Cron last ran 20 minutes ago')
        ->and($mailer->sent[0]->text)->toContain('Advanced, Cron Jobs');
});

it('says nothing about cron on a host where it was never set up', function (): void {
    $paths = new Paths(temporaryFolder());
    [$alerts, $mailer] = testAlerts($paths);

    $alerts->checkCron(new Heartbeat($paths), alertAt(600));

    expect($mailer->sent)->toBe([]);
});

// --- Builds ------------------------------------------------------------------------------

it('emails at once when a build fails, once for that change, and when a build goes live again', function (): void {
    [$alerts, $mailer] = testAlerts(new Paths(temporaryFolder()));
    $commit = str_repeat('a', 40);

    $alerts->buildFailed($commit, alertAt(0));
    $alerts->buildFailed($commit, alertAt(1));
    $alerts->buildLive(alertAt(30));

    expect(subjects($mailer->sent))->toBe([
        'Acme Tours: building the website has stopped working',
        'Acme Tours: building the website works again',
    ])->and($mailer->sent[0]->text)->toContain('change aaaaaaa failed. The change is saved; the website is unchanged.');
});

// --- Who hears about it ---------------------------------------------------------------------

it('sends to ALERTS_TO when it is set', function (): void {
    $config = new AppConfig(name: 'Acme Tours', mailFrom: 'website@example.com', visitReportTo: 'owner@example.com', alertsTo: 'developer@example.com');
    [$alerts, $mailer] = testAlerts(new Paths(temporaryFolder()), $config);

    $alerts->buildFailed(str_repeat('b', 40), alertAt(0));

    expect($mailer->sent[0]->to)->toBe('developer@example.com')
        ->and($mailer->sent[0]->text)->toContain('your address is ALERTS_TO');
});

it('keeps the problem for the doctor when there is nobody to email', function (): void {
    $paths = new Paths(temporaryFolder());
    [$alerts, $mailer] = testAlerts($paths, new AppConfig(name: 'Acme Tours'));
    $alerts->jobFailed('backup', 'No space left.', alertAt(0));
    $alerts->jobFailed('backup', 'No space left.', alertAt(60));

    $results = (new AlertsCheck($alerts, stagingConfig()))->check();

    expect($mailer->sent)->toBe([])
        ->and(statuses($results))->toBe(['warn', 'warn'])
        ->and($results[0]->title)->toBe('The daily backup has stopped working')
        ->and($results[1]->title)->toBe('Alerts go nowhere');
});

it('tells the doctor where alerts go when all is well', function (): void {
    [$alerts] = testAlerts(new Paths(temporaryFolder()));

    [$result] = (new AlertsCheck($alerts, stagingConfig()))->check();

    expect($result->status->value)->toBe('pass')
        ->and($result->detail)->toBe('go to owner@example.com, and nothing has stopped working');
});

it('sends a test alert with alerts:test', function (): void {
    [$alerts, $mailer] = testAlerts(new Paths(temporaryFolder()));
    $stream = fopen('php://memory', 'w+');

    $code = (new AlertsTestCommand($alerts, FrozenClock::at(ALERT_NOW), $mailer))->run(new Input('alerts:test'), new Output($stream, false));
    rewind($stream);

    expect($code)->toBe(0)
        ->and(subjects($mailer->sent))->toBe(['Acme Tours: a test alert'])
        ->and(stream_get_contents($stream))->toContain('Sent a test alert to owner@example.com');
});

// --- When the mailbox fails ---------------------------------------------------------

it('still gets an alert out through the host\'s mail when the mailbox fails, and says why', function (): void {
    $paths = new Paths(temporaryFolder());
    $config = new AppConfig(name: 'Acme Tours', timezone: 'Africa/Johannesburg', mailFrom: 'website@example.com', alertsTo: 'edward@example.com', smtpHost: 'smtp.titan.email');
    $fallback = new Tests\Fakes\FakeMailer();
    [$alerts, $mailbox] = testAlerts($paths, $config, accepts: false, fallback: $fallback);

    $alerts->jobFailed('visit-report', 'The mail server refused the report.', alertAt(0));
    $alerts->jobFailed('visit-report', 'The mail server refused the report.', alertAt(60));

    expect($mailbox->sent)->toBe([])
        ->and(subjects($fallback->sent))->toBe(['Acme Tours: the morning visit report has stopped working'])
        ->and($fallback->sent[0]->text)->toContain('Sent by the host\'s own mail, because email through smtp.titan.email isn\'t working: The fake mail server said no.')
        ->and($fallback->sent[0]->html)->toContain('<strong>Sent by the host\'s own mail</strong>')
        ->and($alerts->open())->toHaveCount(1);
});

it('says nothing about the host\'s mail when the mailbox works', function (): void {
    [$alerts, $mailer] = testAlerts(new Paths(temporaryFolder()));

    $alerts->test(alertAt(0));

    expect($mailer->sent[0]->text)->not->toContain('host\'s own mail');
});
