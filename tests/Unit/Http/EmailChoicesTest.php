<?php

declare(strict_types=1);

use SlimKiller\Alerts\Alerts;
use SlimKiller\Auth\EmailAddress;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\Totp;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Cron\Jobs\VisitReportJob;
use SlimKiller\Mail\Mailer;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use Tests\Fakes\FakeMailer;
use Tests\Fakes\RecordingAfterResponse;

/** Someone who has finished setting up, straight in the database. */
function setUpPerson(UserRepository $users, string $email, string $name, Role $role): SlimKiller\Auth\User
{
    $user = $users->create(EmailAddress::from($email), $name, $role);
    $users->enableTwoFactor($user->id, Totp::newSecret(), 0);

    return $users->byId($user->id);
}

it('starts administrators with alerts and no report, and site owners with a weekly report', function (): void {
    [, $container] = alertingPanel();
    $users = $container->get(UserRepository::class);

    $admin = $users->create(EmailAddress::from('admin@example.com'), 'Admin', Role::Admin);
    $owner = $users->create(EmailAddress::from('owner@example.com'), 'Owner', Role::Owner);

    expect([$admin->visitReport, $admin->alerts])->toBe(['', true])
        ->and([$owner->visitReport, $owner->alerts])->toBe(['0 7 * * 1', false]);
});

it('saves when the visit report comes, and whether alerts come, on Preferences', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);

    $browser->get('/cp/preferences');
    expect($browser->body())->toContain('You don\'t get it now.')
        ->and($browser->body())->toContain('name="alerts" value="1" checked');

    $saved = $browser->post('/cp/preferences', ['form' => 'emails', 'frequency' => 'monthly', 'date' => '0', 'hour' => '8', 'weekday' => '1']);
    expect($saved->getHeaderLine('Location'))->toBe('/cp/preferences?saved=emails#emails');

    $edward = $container->get(UserRepository::class)->byEmail('edward@example.com');
    expect($edward->visitReport)->toBe('0 8 L * *')
        ->and($edward->alerts)->toBeFalse();

    $browser->get('/cp/preferences?saved=emails');
    expect($browser->body())->toContain('Monthly, on the last day at 08:00, next on Monday 30 November, 08:00.');
});

it('sends each reader their own report when it is due, once, and VISIT_REPORT_TO waits', function (): void {
    $mailer = new FakeMailer();
    [, $container] = installedPanel([
        AppConfig::class => new AppConfig(name: 'Acme Tours', timezone: 'Africa/Johannesburg', mailFrom: 'website@example.com', visitReportTo: 'fallback@example.com'),
        Mailer::class => $mailer,
        AfterResponse::class => new RecordingAfterResponse(),
    ]);
    $users = $container->get(UserRepository::class);
    $owner = setUpPerson($users, 'owner@example.com', 'Owner', Role::Owner);
    $daily = setUpPerson($users, 'daily@example.com', 'Daily', Role::Owner);
    $users->setEmails($daily->id, '0 7 * * *', false);
    $job = $container->get(VisitReportJob::class);

    // Monday 23 November, 07:10 in Johannesburg: both are due.
    expect($job->run(new DateTimeImmutable('2026-11-23T05:10:00Z')))
        ->toBe('Sent the visit report: daily:2026-11-22 to daily@example.com; weekly:2026-11-16 to owner@example.com.');
    expect($job->run(new DateTimeImmutable('2026-11-23T05:40:00Z')))->toBe('No visit report is due.')
        ->and($mailer->sent)->toHaveCount(2);

    $weekly = $mailer->sent[1];
    expect($weekly->subject)->toBe('Visits to Acme Tours, 16 to 22 November: 0 visitors')
        ->and($weekly->text)->toContain('That week')
        ->and($weekly->text)->toContain('Average of the 4 weeks before')
        ->and($weekly->text)->toContain('because you chose it in Preferences')
        ->and($mailer->sent[0]->subject)->toBe('Visits to Acme Tours, 22 November: 0 visitors');
});

it('sends alerts to the people who chose them, and to ALERTS_TO only while nobody has', function (): void {
    $mailer = new FakeMailer();
    [, $container] = installedPanel([
        AppConfig::class => new AppConfig(name: 'Acme Tours', timezone: 'Africa/Johannesburg', mailFrom: 'website@example.com', alertsTo: 'setting@example.com'),
        Mailer::class => $mailer,
    ]);
    $alerts = $container->get(Alerts::class);
    expect($alerts->recipients())->toBe(['setting@example.com']);

    $users = $container->get(UserRepository::class);
    setUpPerson($users, 'admin@example.com', 'Admin', Role::Admin);
    setUpPerson($users, 'owner@example.com', 'Owner', Role::Owner);
    expect($alerts->recipients())->toBe(['admin@example.com']);

    expect($alerts->test(new DateTimeImmutable('2026-11-23T05:10:00Z')))->toBeTrue()
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to)->toBe('admin@example.com')
        ->and($mailer->sent[0]->text)->toContain('you chose emails about problems in Preferences');
});
